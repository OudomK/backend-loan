<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\RepaymentTransaction;
use App\Models\LoanModification;
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
    public function calculateCurrentBalance(Loan $loan)
    {
        $totalPrincipal = (float) $loan->amount;

        // Sum principal from installments that are FULLY paid
        $paidPrincipal = $loan->payments()
            ->where('total_paid', '>=', DB::raw('principal_amount + interest_amount + penalty_amount - 0.01'))
            ->sum('principal_amount');

        // Add principal from PARTIALLY paid installments
        $partialPayments = $loan->payments()
            ->where('total_paid', '>', 0)
            ->where('total_paid', '<', DB::raw('principal_amount + interest_amount + penalty_amount - 0.01'))
            ->get();

        foreach ($partialPayments as $p) {
            $remainingForPrincipal = (float) $p->total_paid - (float) $p->interest_amount - (float) $p->penalty_amount;
            if ($remainingForPrincipal > 0) {
                $paidPrincipal += $remainingForPrincipal;
            }
        }

        return max(0, $totalPrincipal - $paidPrincipal);
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

            $oldData = [
                'interest_rate' => $loan->interest_rate,
                'duration_months' => $loan->duration_months,
                'remaining_term' => $loan->payments()->where('total_paid', 0)->count(),
            ];

            $loan->update([
                'interest_rate' => $data['new_rate'],
                'duration_months' => $newTotalTerm,
                'repayment_method' => $data['repayment_method'] ?? $loan->repayment_method,
                'reschedule_fee' => $data['reschedule_fee'] ?? 0,
                'rescheduled_at' => $data['reschedule_date'],
            ]);

            // Record modification history
            LoanModification::create([
                'loan_id' => $loan->id,
                'type' => 'reschedule',
                'old_data' => $oldData,
                'new_data' => [
                    'interest_rate' => $data['new_rate'],
                    'duration_months' => $newTotalTerm,
                    'remaining_term' => $data['remaining_term'],
                ],
                'notes' => 'Rescheduled on ' . $data['reschedule_date'],
            ]);

            $newData = [
                'interest_rate' => (float) $data['new_rate'],
                'duration_months' => (int) $newTotalTerm,
                'remaining_term' => (int) $data['remaining_term'],
            ];

            // Add to system Audit Log
            activity()
                ->performedOn($loan)
                ->withProperties([
                    'old' => $oldData,
                    'attributes' => $newData
                ])
                ->log('Rescheduled loan ' . $loan->loan_code);

            // Delete ALL unpaid installments to start fresh for the remaining term
            // Silence payment logs during this mass update
            Payment::withoutEvents(function () use ($loan, $data, $remainingPrincipal, $paidCount) {
                $deletedInstallments = $loan->payments()->where('total_paid', '<', 0.01)->count();
                $loan->payments()->where('total_paid', '<', 0.01)->forceDelete();

                // Use custom schedule if provided, otherwise regenerate
                if (!empty($data['custom_schedule'])) {
                    $newSchedule = $data['custom_schedule'];
                } else {
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
                }

                // Determine starting payment number based on remaining PAID payments
                $lastPaidNo = $loan->payments()->where('total_paid', '>', 0)->max('payment_number') ?? 0;
                $startingNo = $lastPaidNo + 1;

                foreach ($newSchedule as $index => $item) {
                    // Fix date format if it's in DD/MM/YYYY
                    $paymentDate = $item['date'] ?? ($item['payment_date'] ?? null);
                    if ($paymentDate && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $paymentDate)) {
                        $paymentDate = \DateTime::createFromFormat('d/m/Y', $paymentDate)->format('Y-m-d');
                    }

                    $principalAmt = (float) ($item['principal'] ?? ($item['principal_amount'] ?? 0));
                    $interestAmt = (float) ($item['interest'] ?? ($item['interest_amount'] ?? 0));
                    $feeAmt = (float) ($item['fee'] ?? ($item['fee_amount'] ?? 0));

                    $loan->payments()->create([
                        'payment_number' => $startingNo + $index,
                        'principal_amount' => $principalAmt,
                        'interest_amount' => $interestAmt,
                        'fee_amount' => $feeAmt,
                        'total_due' => round($principalAmt + $interestAmt + $feeAmt, 2),
                        'penalty_amount' => 0,
                        'total_paid' => 0,
                        'payment_date' => $paymentDate,
                        'payment_method' => 'Cash',
                    ]);
                }

                activity('loan_schedule')
                    ->performedOn($loan)
                    ->withProperties([
                        'deleted_installments' => $deletedInstallments,
                        'generated_installments' => count($newSchedule),
                        'remaining_principal' => round($remainingPrincipal, 2),
                        'action' => 'reschedule',
                    ])
                    ->log('Regenerated loan payment schedule');
            });

            return $loan;
        });
    }

    public function refinance(Loan $oldLoan, array $data)
    {
        return DB::transaction(function () use ($oldLoan, $data) {
            $oldBalance = $this->calculateCurrentBalance($oldLoan);

            // Close old loan
            $oldLoan->update(['status' => 'refinanced']);

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

            // Generate new code based on last loan code pattern
            $lastLoan = Loan::orderBy('id', 'desc')->first();
            $lastCode = $lastLoan ? $lastLoan->loan_code : 'QF-000';
            $prefix = 'QF-';
            $number = 1;
            if (preg_match('/([A-Z]+)-(\d+)/', $lastCode, $matches)) {
                $prefix = $matches[1] . '-';
                $number = intval($matches[2]) + 1;
            }
            $newLoan->loan_code = $prefix . str_pad($number, 3, '0', STR_PAD_LEFT);
            $newLoan->disbursed_by_officer_id = $newLoan->loan_officer_id;
            $newLoan->save();

            LoanModification::create([
                'loan_id' => $newLoan->id,
                'type' => 'refinance',
                'old_data' => [
                    'old_loan_id' => $oldLoan->id,
                    'old_balance' => $oldBalance,
                ],
                'new_data' => [
                    'additional_amount' => $data['additional_amount'],
                    'new_total_amount' => $newAmount,
                ],
                'notes' => 'Refinanced from loan ' . $oldLoan->loan_code,
            ]);

            // Add to system Audit Log
            activity()
                ->performedOn($newLoan)
                ->withProperties([
                    'old' => [
                        'loan_code' => $oldLoan->loan_code,
                        'amount' => (float) $oldBalance,
                        'interest_rate' => (float) $oldLoan->interest_rate,
                    ],
                    'attributes' => [
                        'loan_code' => $newLoan->loan_code,
                        'amount' => (float) $newAmount,
                        'interest_rate' => (float) $data['new_rate'],
                    ]
                ])
                ->log('Refinanced loan ' . $newLoan->loan_code);

            // Do not artificially mark future interest as paid. The loan is completed.
            // Do not artificially mark future interest as paid. The loan is completed.
            // Silence payment logs during this mass update
            Payment::withoutEvents(function () use ($oldLoan, $newLoan, $data, $newAmount) {
                $deletedInstallments = $oldLoan->payments()->where('total_paid', '<', 0.01)->count();
                $oldLoan->payments()->where('total_paid', '<', 0.01)->delete();

                // Use custom schedule if provided, otherwise regenerate
                if (!empty($data['custom_schedule'])) {
                    $schedule = $data['custom_schedule'];
                } else {
                    $schedule = $this->calculator->calculateLoanWithDates(
                        $newAmount,
                        $data['new_rate'],
                        $data['new_term'],
                        $data['repayment_method'] ?? $newLoan->repayment_method,
                        $data['start_date'],
                        $newLoan->currency
                    );
                }

                foreach ($schedule as $index => $item) {
                    // Fix date format if it's in DD/MM/YYYY
                    $paymentDate = $item['date'] ?? ($item['payment_date'] ?? null);
                    if ($paymentDate && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $paymentDate)) {
                        $paymentDate = \DateTime::createFromFormat('d/m/Y', $paymentDate)->format('Y-m-d');
                    }

                    $newLoan->payments()->create([
                        'payment_number' => $item['period'] ?? ($item['payment_number'] ?? ($index + 1)),
                        'principal_amount' => $item['principal'] ?? ($item['principal_amount'] ?? 0),
                        'interest_amount' => $item['interest'] ?? ($item['interest_amount'] ?? 0),
                        'penalty_amount' => 0,
                        'total_paid' => 0,
                        'payment_date' => $paymentDate,
                        'payment_method' => 'Cash',
                    ]);
                }

                activity('loan_schedule')
                    ->performedOn($newLoan)
                    ->withProperties([
                        'deleted_installments' => $deletedInstallments,
                        'generated_installments' => count($schedule),
                        'new_amount' => round($newAmount, 2),
                        'action' => 'refinance',
                        'source_loan_id' => $oldLoan->id,
                    ])
                    ->log('Generated refinanced loan payment schedule');
            });

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
