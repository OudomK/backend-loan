<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\RepaymentTransaction;
use Illuminate\Support\Facades\DB;

class LoanService
{
    protected $calculator;

    public function __construct(LoanCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * Calculate the current outstanding principal balance of a loan.
     */
    public function calculateCurrentBalance(Loan $loan): float
    {
        $totalPrincipal = $loan->payments()->sum('principal_amount');

        $paidToPrincipal = $loan->payments()->get()->sum(function ($p) {
            return $p->total_paid > $p->interest_amount ? ($p->total_paid - $p->interest_amount) : 0;
        });

        return (float) ($totalPrincipal - $paidToPrincipal);
    }

    public function reschedule(Loan $loan, array $data)
    {
        return DB::transaction(function () use ($loan, $data) {
            $remainingPrincipal = $this->calculateCurrentBalance($loan);

            // Delete all unpaid future installments
            $loan->payments()->where('total_paid', 0)->delete();

            // Update loan terms
            $paidCount = $loan->payments()->where('total_paid', '>', 0)->count();
            $newTotalTerm = $paidCount + $data['remaining_term'];

            $loan->update([
                'interest_rate' => $data['new_rate'],
                'duration_months' => $newTotalTerm,
                'repayment_method' => $data['repayment_method'] ?? $loan->repayment_method,
            ]);

            // Record transaction
            RepaymentTransaction::create([
                'loan_id' => $loan->id,
                'collector_id' => $loan->loan_officer_id,
                'amount_paid' => 0,
                'principal_paid' => 0,
                'interest_paid' => 0,
                'penalty_paid' => 0,
                'payment_method' => 'Internal',
                'repayment_type' => 'Reschedule',
                'transaction_date' => $data['reschedule_date'],
            ]);

            // Regenerate schedule
            $lastPaidDate = $loan->payments()->where('total_paid', '>', 0)->max('payment_date') ?? $loan->start_date;
            $nextDate = $data['first_payment_date'] ?? date('Y-m-d', strtotime($lastPaidDate . ' +1 month'));

            $newSchedule = $this->calculator->calculateLoanWithDates(
                $remainingPrincipal,
                $data['new_rate'],
                $data['remaining_term'],
                $data['repayment_method'] ?? $loan->repayment_method,
                $nextDate,
                $loan->currency
            );

            $startingNo = $loan->payments()->count() + 1;
            foreach ($newSchedule as $index => $item) {
                $loan->payments()->create([
                    'payment_number' => $startingNo + $index,
                    'principal_amount' => $item['principal'],
                    'interest_amount' => $item['interest'],
                    'penalty_amount' => 0,
                    'total_paid' => 0,
                    'payment_date' => $item['date'],
                    'payment_method' => 'Cash',
                ]);
            }

            return $loan;
        });
    }

    public function refinance(Loan $oldLoan, array $data)
    {
        return DB::transaction(function () use ($oldLoan, $data) {
            $oldBalance = $this->calculateCurrentBalance($oldLoan);

            // Close old loan
            $oldLoan->update(['status' => 'completed']);

            // Create new consolidated loan
            $newAmount = $oldBalance + $data['additional_amount'];
            $newLoan = $oldLoan->replicate(['status', 'amount', 'interest_rate', 'duration_months', 'start_date', 'loan_code']);
            $newLoan->amount = $newAmount;
            $newLoan->interest_rate = $data['new_rate'];
            $newLoan->duration_months = $data['new_term'];
            $newLoan->repayment_method = $data['repayment_method'] ?? $oldLoan->repayment_method;
            $newLoan->start_date = $data['start_date'];
            $newLoan->status = 'active';
            $newLoan->refinanced_from_loan_id = $oldLoan->id;
            $newLoan->refinanced_amount = $oldBalance;
            $newLoan->refinance_fee = $data['refinance_fee'] ?? 0;
            $newLoan->loan_cycle = Loan::where('borrower_id', $oldLoan->borrower_id)->count() + 1;

            // Generate new code
            $lastCode = Loan::max('loan_code');
            $newLoan->loan_code = 'REF-' . (intval(substr($lastCode, 4)) + 1);
            $newLoan->disbursed_by_officer_id = $newLoan->loan_officer_id;
            $newLoan->save();

            // Mark old installments as paid
            $oldLoan->payments->each(function (\App\Models\Payment $p) {
                $p->update(['total_paid' => $p->principal_amount + $p->interest_amount]);
            });

            // Record transaction
            RepaymentTransaction::create([
                'loan_id' => $oldLoan->id,
                'collector_id' => $oldLoan->loan_officer_id,
                'amount_paid' => $oldBalance,
                'principal_paid' => $oldBalance,
                'interest_paid' => 0,
                'penalty_paid' => 0,
                'payment_method' => 'Internal/Refinance',
                'repayment_type' => 'Refinance',
                'transaction_date' => $data['start_date'],
            ]);

            // Generate new schedule
            $schedule = $this->calculator->calculateLoanWithDates(
                $newAmount,
                $data['new_rate'],
                $data['new_term'],
                $data['repayment_method'] ?? $newLoan->repayment_method,
                $data['start_date'],
                $newLoan->currency
            );

            foreach ($schedule as $item) {
                $newLoan->payments()->create([
                    'payment_number' => $item['period'],
                    'principal_amount' => $item['principal'],
                    'interest_amount' => $item['interest'],
                    'penalty_amount' => 0,
                    'total_paid' => 0,
                    'payment_date' => $item['date'],
                    'payment_method' => 'Cash',
                ]);
            }

            return $newLoan;
        });
    }

    public function previewModification(Loan $loan, array $data)
    {
        $type = $data['type'];
        $amount = 0;
        $term = $data['term']; // remaining_term or new_term
        $rate = $data['new_rate'];
        $method = $data['repayment_method'] ?? $loan->repayment_method;
        $startDate = $data['start_date']; // first_payment_date or start_date

        if ($type === 'reschedule') {
            $amount = $this->calculateCurrentBalance($loan);
        } else {
            // Refinance
            $oldBalance = $this->calculateCurrentBalance($loan);
            $amount = $oldBalance + ($data['additional_amount'] ?? 0);
        }

        return $this->calculator->calculateLoanWithDates(
            $amount,
            $rate,
            $term,
            $method,
            $startDate,
            $loan->currency
        );
    }
}
