<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\RepaymentTransaction;
use App\Models\LoanModification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class LoanService
{
    protected LoanCalculator $calculator;

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

        $paidPrincipal = RepaymentTransaction::where('loan_id', $loan->id)
            ->where('repayment_type', '!=', 'Withdraw')
            ->sum('principal_paid');

        $paidOffAmount = RepaymentTransaction::where('loan_id', $loan->id)
            ->where('repayment_type', '!=', 'Withdraw')
            ->sum('paid_off_amount');

        return max(0, $totalPrincipal - $paidPrincipal - $paidOffAmount);
    }

    public function reschedule(Loan $loan, array $data)
    {
        return DB::transaction(function () use ($loan, $data) {
            $payOffPrincipal = (float) ($data['pay_off_principal'] ?? 0);
            $accruedInterest = (float) ($data['accrued_interest'] ?? 0);
            $totalAmountPaid = $payOffPrincipal + $accruedInterest;

            if ($totalAmountPaid > 0) {
                // Record transaction
                $transaction = RepaymentTransaction::create([
                    'loan_id' => $loan->id,
                    'collector_id' => Auth::id() ?? $loan->loan_officer_id ?? 1,
                    'amount_paid' => $totalAmountPaid,
                    'principal_paid' => $payOffPrincipal,
                    'interest_paid' => $accruedInterest,
                    'penalty_paid' => 0,
                    'fee_paid' => 0,
                    'payment_method' => 'Cash',
                    'repayment_type' => 'Partial',
                    'transaction_date' => $data['reschedule_date'],
                    'paid_off_amount' => 0,
                    'recovery_amount' => 0,
                    'withdrawn_prepayment' => 0,
                ]);

                // Apply payment to installments
                $totalToDistribute = $totalAmountPaid;
                $installments = $loan->payments()
                    ->whereRaw('total_paid < (COALESCE(principal_amount, 0) + COALESCE(interest_amount, 0) + COALESCE(fee_amount, 0) - 0.01)')
                    ->orderBy('payment_date', 'asc')
                    ->get();
                
                foreach ($installments as $inst) {
                    if ($totalToDistribute <= 0.001) break;
                    
                    $due = (float) $inst->principal_amount + (float) $inst->interest_amount + (float) ($inst->fee_amount ?? 0) - (float) $inst->total_paid;
                    $applied = min($totalToDistribute, $due);
                    $inst->total_paid = round((float) $inst->total_paid + $applied, 2);
                    $inst->repayment_transaction_id = $transaction->id;
                    $inst->save();
                    
                    $totalToDistribute -= $applied;
                }
                
                // If there's still money left, store as prepayment on the last updated installment
                if ($totalToDistribute > 0.001 && isset($inst)) {
                    $inst->total_paid = round((float) $inst->total_paid + $totalToDistribute, 2);
                    $inst->prepayment = round((float) ($inst->prepayment ?? 0) + $totalToDistribute, 2);
                    $inst->save();
                }
            }

            $remainingPrincipal = $this->calculateCurrentBalance($loan);
            $originalLoanCode = $loan->loan_code;

            // Delete all unpaid future installments of the old loan
            $loan->payments()->where('total_paid', '<', 0.01)->forceDelete();

            $oldData = [
                'interest_rate' => $loan->interest_rate,
                'duration_months' => $loan->duration_months,
                'remaining_term' => $loan->payments()->count(), // Only paid ones are left
                'balance' => $remainingPrincipal,
            ];

            // Mark old loan as rescheduled
            $loan->update([
                'status' => 'rescheduled',
                'rescheduled_at' => $data['reschedule_date'],
                'monthly_payment' => 0,
                'loan_code' => $loan->loan_code . '-Rescheduled',
            ]);

            // Create new loan
            $firstPaymentDate = $data['first_payment_date'] ?? date('Y-m-d', strtotime($data['reschedule_date'] . ' +1 month'));
            $newLoan = Loan::create([
                'borrower_id' => $loan->borrower_id,
                'co_borrower_id' => $loan->co_borrower_id,
                'guarantor_id' => $loan->guarantor_id,
                'loan_officer_id' => $loan->loan_officer_id,
                'disbursed_by_officer_id' => Auth::id() ?? $loan->disbursed_by_officer_id,
                'product_id' => $loan->product_id,
                'amount' => $remainingPrincipal,
                'disbursed_amount' => $remainingPrincipal,
                'interest_rate' => $data['new_rate'],
                'duration_months' => $data['remaining_term'],
                'monthly_payment' => 0,
                'start_date' => $firstPaymentDate,
                'status' => 'active',
                'currency' => $loan->currency,
                'repayment_method' => $data['repayment_method'] ?? $loan->repayment_method,
                'purpose' => 'Reschedule of ' . $originalLoanCode,
                'loan_code' => $originalLoanCode, // Original loan code without suffix
                'loan_cycle' => $loan->loan_cycle, // Same cycle
                'admin_fee' => $loan->admin_fee ?? 0,
                'admin_fee_type' => $loan->admin_fee_type ?? 'one_time',
                'reschedule_fee' => $data['reschedule_fee'] ?? 0,
                'refinanced_from_loan_id' => $loan->id, // To keep the chain
            ]);

            // Copy collaterals
            foreach ($loan->collaterals as $collateral) {
                $newCollateral = $collateral->replicate();
                $newCollateral->loan_id = $newLoan->id;
                $newCollateral->save();
            }

            // Record modification history linked to NEW LOAN
            LoanModification::create([
                'loan_id' => $newLoan->id,
                'type' => 'reschedule',
                'old_data' => $oldData,
                'new_data' => [
                    'interest_rate' => $data['new_rate'],
                    'duration_months' => $data['remaining_term'],
                    'remaining_term' => $data['remaining_term'],
                    'new_amount' => $remainingPrincipal,
                ],
                'notes' => 'Rescheduled on ' . $data['reschedule_date'],
            ]);

            // Generate schedule for new loan
            if (!empty($data['custom_schedule'])) {
                $newSchedule = $data['custom_schedule'];
            } else {
                $newSchedule = $this->calculator->calculateLoanWithDates(
                    $remainingPrincipal,
                    $data['new_rate'],
                    $data['remaining_term'],
                    $data['repayment_method'] ?? $newLoan->repayment_method,
                    $firstPaymentDate,
                    $newLoan->currency,
                    $newLoan->admin_fee ?? 0,
                    $newLoan->admin_fee_type ?? 'one_time'
                );
            }

            foreach ($newSchedule as $index => $item) {
                // Fix date format if it's in DD/MM/YYYY
                $paymentDate = $item['date'] ?? ($item['payment_date'] ?? null);
                if ($paymentDate && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $paymentDate)) {
                    $paymentDate = \DateTime::createFromFormat('d/m/Y', $paymentDate)->format('Y-m-d');
                }

                $principalAmt = (float) ($item['principal'] ?? ($item['principal_amount'] ?? 0));
                $interestAmt = (float) ($item['interest'] ?? ($item['interest_amount'] ?? 0));
                $feeAmt = (float) ($item['fee'] ?? ($item['fee_amount'] ?? 0));

                $newLoan->payments()->create([
                    'payment_number' => $index + 1,
                    'principal_amount' => $principalAmt,
                    'interest_amount' => $interestAmt,
                    'fee_amount' => $feeAmt,
                    'outstanding_balance' => isset($item['balance']) ? (float) $item['balance'] : (isset($item['remaining_balance']) ? (float) $item['remaining_balance'] : (isset($item['outstanding_balance']) ? (float) $item['outstanding_balance'] : null)),
                    'penalty_amount' => 0,
                    'total_paid' => 0,
                    'payment_date' => $paymentDate,
                    'payment_method' => 'Cash',
                ]);
            }

            // Update monthly payment approximation
            $firstPayment = $newLoan->payments()->orderBy('payment_number', 'asc')->first();
            if ($firstPayment) {
                $newLoan->update([
                    'monthly_payment' => $firstPayment->principal_amount + $firstPayment->interest_amount + $firstPayment->fee_amount
                ]);
            }

            activity('loan_schedule')
                ->performedOn($newLoan)
                ->withProperties([
                    'generated_installments' => count($newSchedule),
                    'remaining_principal' => round($remainingPrincipal, 2),
                    'action' => 'reschedule',
                ])
                ->log('Generated rescheduled loan payment schedule');

            return $newLoan;
        });
    }

    public function refinance(Loan $oldLoan, array $data)
    {
        return DB::transaction(function () use ($oldLoan, $data) {
            $oldBalance = $this->calculateCurrentBalance($oldLoan);

            $penaltyPaid = (float) ($data['penalty_amount'] ?? 0);
            if ($penaltyPaid > 0) {
                $transaction = RepaymentTransaction::create([
                    'loan_id' => $oldLoan->id,
                    'collector_id' => Auth::id() ?? $oldLoan->loan_officer_id ?? 1,
                    'amount_paid' => $penaltyPaid,
                    'principal_paid' => 0,
                    'interest_paid' => 0,
                    'penalty_paid' => $penaltyPaid,
                    'fee_paid' => 0,
                    'payment_method' => 'Cash',
                    'repayment_type' => 'Partial',
                    'transaction_date' => $data['start_date'],
                    'paid_off_amount' => 0,
                    'recovery_amount' => 0,
                    'withdrawn_prepayment' => 0,
                ]);

                $penaltyCategory = \App\Models\RevenueCategory::where('slug', 'penalty_income')->first()
                    ?? \App\Models\RevenueCategory::where('name', 'LIKE', '%Penalty%')->first();
                if ($penaltyCategory) {
                    \App\Models\Revenue::create([
                        'revenue_category_id' => $penaltyCategory->id,
                        'loan_id' => $oldLoan->id,
                        'repayment_transaction_id' => $transaction->id,
                        'amount' => $penaltyPaid,
                        'currency' => $oldLoan->currency,
                        'transaction_date' => $data['start_date'],
                        'payment_method' => 'Cash',
                        'description' => "Penalty paid during refinance for loan {$oldLoan->loan_code}",
                        'status' => 'completed',
                    ]);
                }
            }

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
            $newCycle = Loan::where('borrower_id', $oldLoan->borrower_id)->count() + 1;
            $newLoan->loan_cycle = $newCycle;

            $baseCode = preg_replace('/-C\d+$/', '', $oldLoan->loan_code);
            $newLoan->loan_code = $baseCode . '-C' . $newCycle;
            $newLoan->disbursed_by_officer_id = $newLoan->loan_officer_id;
            
            // Lock penalty rate based on current settings
            $settingKey = $newLoan->currency === 'KHR' ? 'default_penalty_khr' : 'default_penalty_usd';
            $defaultRate = $newLoan->currency === 'KHR' ? 10000 : 2.5;
            $newLoan->penalty_rate = \App\Models\Setting::where('key', $settingKey)->value('value') ?? $defaultRate;

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

                // Close out partially paid installments on old loan
                $partialPayments = $oldLoan->payments()
                    ->where('total_paid', '>', 0)
                    ->where('total_paid', '<', DB::raw('principal_amount + interest_amount + penalty_amount - 0.01'))
                    ->get();
                
                foreach ($partialPayments as $p) {
                    $existingFeePaid = (float) ($p->fee_paid ?? 0);
                    $existingTotalPaid = (float) $p->total_paid;
                    
                    $alreadyPaidToPrinInt = max(0, $existingTotalPaid - $existingFeePaid);
                    $interestPaidSoFar = min((float) $p->interest_amount, $alreadyPaidToPrinInt);
                    $principalPaidSoFar = max(0, $alreadyPaidToPrinInt - $interestPaidSoFar);
                    
                    $p->update([
                        'principal_amount' => $principalPaidSoFar,
                        'interest_amount' => $interestPaidSoFar,
                        'fee_amount' => $existingFeePaid,
                    ]);
                }

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
                        $newLoan->currency,
                        $newLoan->admin_fee ?? 0,
                        $newLoan->admin_fee_type ?? 'one_time'
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
                        'fee_amount' => $item['fee'] ?? ($item['fee_amount'] ?? 0),
                        'outstanding_balance' => isset($item['balance']) ? (float) $item['balance'] : (isset($item['remaining_balance']) ? (float) $item['remaining_balance'] : (isset($item['outstanding_balance']) ? (float) $item['outstanding_balance'] : null)),
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
            $paydown = (float) ($data['paydown_amount'] ?? 0);
            $amount = max(0, $amount - $paydown);
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
            $loan->currency,
            $loan->admin_fee ?? 0,
            $loan->admin_fee_type ?? 'one_time'
        );
    }
}
