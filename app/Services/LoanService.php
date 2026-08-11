<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanModification;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RepaymentTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanService
{
    public function __construct(private readonly LoanScheduleService $scheduleService) {}

    /**
     * Calculate the current outstanding principal balance of a loan.
     */
    public function calculateCurrentBalance(Loan $loan): float
    {
        $loan->loadMissing('payments');
        $basePrincipal = $loan->getBasePrincipalForOS();
        $principalMovement = (float) RepaymentTransaction::query()
            ->where('loan_id', $loan->id)
            ->selectRaw(
                'COALESCE(SUM(COALESCE(principal_paid, 0) + COALESCE(prepayment_paid, 0) '
                .' + COALESCE(paid_off_amount, 0) - COALESCE(withdrawn_prepayment, 0)), 0) AS aggregate'
            )
            ->value('aggregate');

        return round(max(0, $basePrincipal - $principalMovement), 2);
    }

    public function reschedule(Loan $loan, array $data)
    {
        return DB::transaction(function () use ($loan, $data) {
            $loan = $this->lockActiveCycle($loan);
            $payOffPrincipal = (float) ($data['pay_off_principal'] ?? 0);
            $accruedInterest = (float) ($data['accrued_interest'] ?? 0);

            if ($payOffPrincipal > $this->calculateCurrentBalance($loan) + 0.001) {
                throw ValidationException::withMessages([
                    'pay_off_principal' => 'Principal payment cannot exceed the selected cycle outstanding balance.',
                ]);
            }

            if ($payOffPrincipal + $accruedInterest > 0.001) {
                $this->applyModificationPayment(
                    $loan,
                    $payOffPrincipal,
                    $accruedInterest,
                    'Reschedule',
                    $data['reschedule_date']
                );
            }

            $remainingPrincipal = $this->calculateCurrentBalance($loan);
            if ($remainingPrincipal <= 0.001) {
                throw ValidationException::withMessages([
                    'pay_off_principal' => 'A fully paid cycle cannot be rescheduled.',
                ]);
            }

            $oldData = [
                'source_cycle_id' => $loan->id,
                'loan_code' => $loan->loan_code,
                'loan_cycle' => (int) $loan->loan_cycle,
                'interest_rate' => $loan->interest_rate,
                'duration_months' => $loan->duration_months,
                'balance' => $remainingPrincipal,
            ];

            $firstPaymentDate = $data['first_payment_date'] ?? date('Y-m-d', strtotime($data['reschedule_date'].' +1 month'));
            $newCycle = $this->nextBorrowerCycle($loan);
            $newLoan = $this->createNextCycle($loan, [
                'amount' => $remainingPrincipal,
                'cash_disbursed' => 0,
                'interest_rate' => (float) $data['new_rate'],
                'term' => (int) $data['remaining_term'],
                'start_date' => $firstPaymentDate,
                'repayment_method' => $data['repayment_method'] ?? $loan->repayment_method,
                'cycle' => $newCycle,
                'type' => 'reschedule',
                'reschedule_fee' => (float) ($data['reschedule_fee'] ?? 0),
            ]);

            LoanModification::create([
                'loan_id' => $newLoan->id,
                'type' => 'reschedule',
                'old_data' => $oldData,
                'new_data' => [
                    'interest_rate' => $data['new_rate'],
                    'duration_months' => $data['remaining_term'],
                    'remaining_term' => $data['remaining_term'],
                    'new_amount' => $remainingPrincipal,
                    'new_cycle' => $newCycle,
                ],
                'notes' => 'Rescheduled on '.$data['reschedule_date'],
            ]);

            if (! empty($data['custom_schedule'])) {
                $newSchedule = $data['custom_schedule'];
            } else {
                $newSchedule = $this->scheduleService->generate([
                    'amount' => $remainingPrincipal,
                    'interest_rate' => $data['new_rate'],
                    'duration_months' => $data['remaining_term'],
                    'repayment_method' => $data['repayment_method'] ?? $newLoan->repayment_method,
                    'start_date' => $firstPaymentDate,
                    'currency' => $newLoan->currency,
                    'admin_fee' => $newLoan->admin_fee ?? 0,
                    'admin_fee_type' => $newLoan->admin_fee_type ?? 'one_time',
                ]);
            }
            $this->persistCycleSchedule($newLoan, $newSchedule, $remainingPrincipal);

            // Close only the selected cycle. Other active loans owned by this borrower are untouched.
            $loan->update([
                'status' => 'rescheduled',
                'rescheduled_at' => $data['reschedule_date'],
                'monthly_payment' => 0,
            ]);

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
            $oldLoan = $this->lockActiveCycle($oldLoan);
            $oldBalance = $this->calculateCurrentBalance($oldLoan);
            $additionalAmount = (float) $data['additional_amount'];
            $newAmount = round($oldBalance + $additionalAmount, 2);

            if ($oldBalance <= 0.001 || $newAmount <= 0.001) {
                throw ValidationException::withMessages([
                    'additional_amount' => 'The refinanced cycle principal must be greater than zero.',
                ]);
            }

            $penaltyPaid = (float) ($data['penalty_amount'] ?? 0);
            $penaltyDue = $oldLoan->currentPenaltyDue(Carbon::parse($data['start_date'])->startOfDay());
            if ($penaltyPaid > $penaltyDue + 0.001) {
                throw ValidationException::withMessages([
                    'penalty_amount' => 'Penalty payment cannot exceed the selected cycle penalty due.',
                ]);
            }

            if ($penaltyPaid > 0) {
                $transaction = RepaymentTransaction::create([
                    'loan_id' => $oldLoan->id,
                    'collector_id' => $oldLoan->loan_officer_id,
                    'amount_paid' => $penaltyPaid,
                    'principal_paid' => 0,
                    'interest_paid' => 0,
                    'penalty_paid' => $penaltyPaid,
                    'fee_paid' => 0,
                    'payment_method' => 'Cash',
                    'repayment_type' => 'Refinance',
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

            $newCycle = $this->nextBorrowerCycle($oldLoan);
            $newLoan = $this->createNextCycle($oldLoan, [
                'amount' => $newAmount,
                'cash_disbursed' => $additionalAmount,
                'interest_rate' => (float) $data['new_rate'],
                'term' => (int) $data['new_term'],
                'start_date' => $data['start_date'],
                'repayment_method' => $data['repayment_method'] ?? $oldLoan->repayment_method,
                'cycle' => $newCycle,
                'type' => 'refinance',
                'refinance_fee' => (float) ($data['refinance_fee'] ?? 0),
                'refinanced_amount' => $oldBalance,
            ]);

            LoanModification::create([
                'loan_id' => $newLoan->id,
                'type' => 'refinance',
                'old_data' => [
                    'old_loan_id' => $oldLoan->id,
                    'old_balance' => $oldBalance,
                ],
                'new_data' => [
                    'additional_amount' => $additionalAmount,
                    'new_total_amount' => $newAmount,
                    'new_cycle' => $newCycle,
                ],
                'notes' => 'Refinanced from loan '.$oldLoan->loan_code,
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
                    ],
                ])
                ->log('Refinanced loan '.$newLoan->loan_code);

            $schedule = ! empty($data['custom_schedule'])
                ? $data['custom_schedule']
                : $this->scheduleService->generate([
                    'amount' => $newAmount,
                    'interest_rate' => $data['new_rate'],
                    'duration_months' => $data['new_term'],
                    'repayment_method' => $data['repayment_method'] ?? $newLoan->repayment_method,
                    'start_date' => $data['start_date'],
                    'currency' => $newLoan->currency,
                    'admin_fee' => $newLoan->admin_fee ?? 0,
                    'admin_fee_type' => $newLoan->admin_fee_type ?? 'one_time',
                ]);
            $this->persistCycleSchedule($newLoan, $schedule, $newAmount);

            // Preserve the selected cycle schedule and transactions for audit/history.
            // Only this cycle is closed; the borrower's other active loans remain untouched.
            $oldLoan->update([
                'status' => 'refinanced',
                'monthly_payment' => 0,
            ]);

            activity('loan_schedule')
                ->performedOn($newLoan)
                ->withProperties([
                    'generated_installments' => count($schedule),
                    'new_amount' => round($newAmount, 2),
                    'cash_disbursed' => round($additionalAmount, 2),
                    'action' => 'refinance',
                    'source_loan_id' => $oldLoan->id,
                ])
                ->log('Generated refinanced loan cycle payment schedule');

            return $newLoan;
        });
    }

    private function lockActiveCycle(Loan $loan): Loan
    {
        // Lock every cycle for this borrower in a consistent order. This serializes
        // simultaneous modifications of two different active loans owned by the
        // same customer and prevents duplicate next-cycle numbers.
        $lockedLoan = Loan::query()
            ->where('borrower_id', $loan->borrower_id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->firstWhere('id', $loan->getKey());

        if (! $lockedLoan) {
            throw ValidationException::withMessages([
                'loan_id' => 'The selected loan cycle was not found.',
            ]);
        }

        if ($lockedLoan->status !== 'active') {
            throw ValidationException::withMessages([
                'loan_id' => 'Only an active loan cycle can be rescheduled or refinanced.',
            ]);
        }

        return $lockedLoan->loadMissing(['payments', 'collaterals']);
    }

    private function nextBorrowerCycle(Loan $loan): int
    {
        $cycles = Loan::query()
            ->withTrashed()
            ->where('borrower_id', $loan->borrower_id)
            ->lockForUpdate()
            ->pluck('loan_cycle');

        return max((int) $loan->loan_cycle, (int) ($cycles->max() ?? 0)) + 1;
    }

    /**
     * Create the next database row in the same borrower's cycle sequence.
     * It is a continuation cycle, not an independent customer loan application.
     *
     * @param  array<string, mixed>  $data
     */
    private function createNextCycle(Loan $source, array $data): Loan
    {
        $cycle = (int) $data['cycle'];
        $baseCode = preg_replace('/-C\d+(?:-(?:Rescheduled|Refinanced))?$/i', '', (string) $source->loan_code);
        $next = $source->replicate();
        $sourceFeeType = trim((string) ($source->admin_fee_type ?? '')) ?: 'one_time';
        $carryMonthlyFee = $sourceFeeType === 'monthly';

        $next->fill([
            'amount' => round((float) $data['amount'], 2),
            'disbursed_amount' => round((float) $data['cash_disbursed'], 2),
            'total_paid' => 0,
            'interest_rate' => (float) $data['interest_rate'],
            'duration_months' => (int) $data['term'],
            'monthly_payment' => 0,
            'monthly_interest' => round(
                (float) $data['amount'] * (float) $data['interest_rate'] / 100,
                2
            ),
            'start_date' => $data['start_date'],
            'status' => 'active',
            'repayment_method' => $data['repayment_method'],
            'loan_code' => $baseCode.'-C'.$cycle,
            'loan_cycle' => $cycle,
            'admin_fee' => $carryMonthlyFee ? (float) $source->admin_fee : 0,
            'admin_fee_type' => $carryMonthlyFee ? 'monthly' : 'one_time',
            'refinanced_from_loan_id' => $source->id,
            'refinanced_amount' => (float) ($data['refinanced_amount'] ?? 0),
            'refinance_fee' => (float) ($data['refinance_fee'] ?? 0),
            'reschedule_fee' => (float) ($data['reschedule_fee'] ?? 0),
            'rescheduled_at' => null,
            'aging' => 0,
            'locked_aging' => 0,
            'accumulated_penalty' => 0,
            'late_since_date' => null,
            'penalty_late_since_date' => null,
            'written_off_at' => null,
            'write_off_reason' => null,
            'classify_wo' => null,
            'write_off_balance' => 0,
            'recovery_amount' => 0,
            'submitted_by' => null,
            'checked_by' => null,
            'verified_by' => null,
            'approved_by' => null,
            'checked_at' => null,
            'verified_at' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ]);

        // User IDs and loan-officer IDs are different entities. Keep the assigned
        // officer from the selected cycle instead of writing Auth::id() here.
        $next->disbursed_by_officer_id = $source->loan_officer_id
            ?? $source->disbursed_by_officer_id;
        $next->save();

        foreach ($source->collaterals as $collateral) {
            $copy = $collateral->replicate();
            $copy->loan_id = $next->id;
            $copy->save();
        }

        return $next;
    }

    /**
     * Preserve an auditable allocation for cash paid immediately before a reschedule.
     */
    private function applyModificationPayment(
        Loan $loan,
        float $principalTarget,
        float $interestTarget,
        string $type,
        string $transactionDate
    ): RepaymentTransaction {
        $installments = $loan->payments()
            ->orderBy('payment_date')
            ->orderBy('payment_number')
            ->lockForUpdate()
            ->get();

        $outstandingInterest = $installments->sum(function (Payment $payment): float {
            $paidToPrincipalAndInterest = max(
                0,
                (float) $payment->total_paid - (float) ($payment->fee_paid ?? 0)
            );
            $interestPaid = min((float) $payment->interest_amount, $paidToPrincipalAndInterest);

            return max(0, (float) $payment->interest_amount - $interestPaid);
        });

        if ($interestTarget > $outstandingInterest + 0.001) {
            throw ValidationException::withMessages([
                'accrued_interest' => 'Interest payment cannot exceed the selected cycle outstanding interest.',
            ]);
        }

        $transaction = RepaymentTransaction::create([
            'loan_id' => $loan->id,
            'collector_id' => $loan->loan_officer_id,
            'amount_paid' => round($principalTarget + $interestTarget, 2),
            'principal_paid' => round($principalTarget, 2),
            'interest_paid' => round($interestTarget, 2),
            'penalty_paid' => 0,
            'fee_paid' => 0,
            'payment_method' => 'Cash',
            'repayment_type' => $type,
            'transaction_date' => $transactionDate,
            'paid_off_amount' => 0,
            'recovery_amount' => 0,
            'withdrawn_prepayment' => 0,
        ]);

        $remainingInterest = round($interestTarget, 2);
        $remainingPrincipal = round($principalTarget, 2);
        $touchedPayments = collect();

        foreach ($installments as $payment) {
            if ($remainingInterest <= 0.001 && $remainingPrincipal <= 0.001) {
                break;
            }

            $existingTotalPaid = (float) $payment->total_paid;
            $paidToPrincipalAndInterest = max(
                0,
                $existingTotalPaid - (float) ($payment->fee_paid ?? 0)
            );
            $interestPaid = min((float) $payment->interest_amount, $paidToPrincipalAndInterest);
            $principalPaid = max(0, $paidToPrincipalAndInterest - $interestPaid);
            $interestApplied = round(min(
                $remainingInterest,
                max(0, (float) $payment->interest_amount - $interestPaid)
            ), 2);
            $principalApplied = round(min(
                $remainingPrincipal,
                max(0, (float) $payment->principal_amount - $principalPaid)
            ), 2);
            $applied = round($interestApplied + $principalApplied, 2);

            if ($applied <= 0.001) {
                continue;
            }

            $payment->update([
                'total_paid' => round($existingTotalPaid + $applied, 2),
                'repayment_transaction_id' => $transaction->id,
            ]);
            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'repayment_transaction_id' => $transaction->id,
                'amount_applied' => $applied,
                'fee_applied' => 0,
                'interest_applied' => $interestApplied,
                'principal_applied' => $principalApplied,
                'penalty_applied' => 0,
            ]);
            $touchedPayments->push($payment->fresh());

            $remainingInterest = round($remainingInterest - $interestApplied, 2);
            $remainingPrincipal = round($remainingPrincipal - $principalApplied, 2);
        }

        if ($remainingInterest > 0.001 || $remainingPrincipal > 0.001) {
            throw ValidationException::withMessages([
                'payment' => 'The reschedule payment could not be fully allocated to the selected cycle.',
            ]);
        }

        $loan->update(['total_paid' => $loan->payments()->sum('total_paid')]);
        $timingService = app(PaymentSettlementTimingService::class);
        $touchedPayments->each(
            fn (Payment $payment) => $timingService->sync($payment)
        );

        return $transaction;
    }

    /** @param array<int, array<string, mixed>> $schedule */
    private function persistCycleSchedule(Loan $loan, array $schedule, float $expectedPrincipal): void
    {
        if ($schedule === []) {
            throw ValidationException::withMessages([
                'custom_schedule' => 'The new cycle must contain at least one repayment installment.',
            ]);
        }

        $principalTotal = 0.0;
        $lastPaymentDate = null;
        foreach ($schedule as $index => $item) {
            $paymentDate = $item['date'] ?? ($item['payment_date'] ?? null);
            if ($paymentDate && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', (string) $paymentDate)) {
                $parsedDate = \DateTime::createFromFormat('d/m/Y', (string) $paymentDate);
                $paymentDate = $parsedDate ? $parsedDate->format('Y-m-d') : null;
            }
            if (! $paymentDate || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $paymentDate)) {
                throw ValidationException::withMessages([
                    "custom_schedule.{$index}.date" => 'Every schedule row must have a valid payment date.',
                ]);
            }

            $principal = round((float) ($item['principal'] ?? ($item['principal_amount'] ?? 0)), 2);
            $interest = round((float) ($item['interest'] ?? ($item['interest_amount'] ?? 0)), 2);
            $fee = round((float) ($item['fee'] ?? ($item['fee_amount'] ?? 0)), 2);
            if ($principal < 0 || $interest < 0 || $fee < 0) {
                throw ValidationException::withMessages([
                    "custom_schedule.{$index}" => 'Schedule amounts cannot be negative.',
                ]);
            }

            $principalTotal += $principal;
            $lastPaymentDate = $paymentDate;
            $loan->payments()->create([
                'payment_number' => $item['period'] ?? ($item['payment_number'] ?? ($index + 1)),
                'principal_amount' => $principal,
                'interest_amount' => $interest,
                'fee_amount' => $fee,
                'outstanding_balance' => isset($item['balance'])
                    ? (float) $item['balance']
                    : (isset($item['remaining_balance'])
                        ? (float) $item['remaining_balance']
                        : (isset($item['outstanding_balance']) ? (float) $item['outstanding_balance'] : null)),
                'penalty_amount' => 0,
                'total_paid' => 0,
                'payment_date' => $paymentDate,
                'payment_method' => 'Cash',
            ]);
        }

        if (abs($principalTotal - $expectedPrincipal) > 0.02) {
            throw ValidationException::withMessages([
                'custom_schedule' => 'Schedule principal total must equal the new cycle principal.',
            ]);
        }

        $firstPayment = $loan->payments()->orderBy('payment_number')->first();
        $loan->update([
            'monthly_payment' => $firstPayment
                ? round(
                    (float) $firstPayment->principal_amount
                    + (float) $firstPayment->interest_amount
                    + (float) $firstPayment->fee_amount,
                    2
                )
                : 0,
            'maturity_date' => $lastPaymentDate,
        ]);
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

        return $this->scheduleService->generate([
            'amount' => $amount,
            'interest_rate' => $rate,
            'duration_months' => $term,
            'repayment_method' => $method,
            'start_date' => $startDate,
            'currency' => $loan->currency,
            'admin_fee' => $loan->admin_fee ?? 0,
            'admin_fee_type' => $loan->admin_fee_type ?? 'one_time',
        ]);
    }
}
