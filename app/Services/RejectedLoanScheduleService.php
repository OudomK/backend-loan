<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanApproval;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RejectedLoanScheduleService
{
    public function __construct(private readonly LoanScheduleService $scheduleService) {}

    /**
     * Build and validate a replacement schedule without changing the database.
     *
     * @return array{schedule: array<int, array<string, mixed>>, summary: array<string, float|int|string>}
     */
    public function preview(Loan $loan): array
    {
        $this->assertCorrectable($loan);

        $schedule = $this->scheduleService->generate($this->scheduleInput($loan));
        $summary = $this->validateGeneratedSchedule($loan, $schedule);

        return compact('schedule', 'summary');
    }

    public function regenerate(Loan $loan, User $user): Loan
    {
        return DB::transaction(function () use ($loan, $user): Loan {
            $loan = Loan::query()->whereKey($loan->getKey())->lockForUpdate()->firstOrFail();
            $preview = $this->preview($loan);
            $oldSummary = $this->persistedSummary($loan);

            $loan->payments()->get()->each->delete();
            $this->scheduleService->persist($loan, $preview['schedule']);

            $loan->update([
                'payment_frequency' => LoanScheduleService::canonicalPaymentFrequency(
                    (string) $loan->repayment_method,
                    $loan->payment_frequency
                ),
                'schedule_needs_recalculation' => false,
                'schedule_recalculated_at' => now(),
                'schedule_recalculated_by' => $user->id,
            ]);

            activity('loan_schedule')
                ->performedOn($loan)
                ->causedBy($user)
                ->withProperties([
                    'old_schedule' => $oldSummary,
                    'new_schedule' => $preview['summary'],
                    'repayment_method' => $loan->repayment_method,
                ])
                ->log('Regenerated rejected loan schedule');

            return $loan->fresh(['payments']);
        });
    }

    public function assertReadyForResubmission(Loan $loan): void
    {
        if ((bool) $loan->schedule_needs_recalculation) {
            throw new \InvalidArgumentException(
                'The repayment schedule must be regenerated before this loan can be resubmitted.'
            );
        }

        $this->assertNoRepaymentActivity($loan);

        $payments = $loan->payments()->orderBy('payment_number')->get();
        if ($payments->isEmpty()) {
            throw new \InvalidArgumentException('A repayment schedule is required before resubmission.');
        }

        $principal = round((float) $payments->sum('principal_amount'), 2);
        if (abs($principal - (float) $loan->amount) > 0.01) {
            throw new \InvalidArgumentException(
                'Scheduled principal does not match the loan amount. Regenerate the schedule before resubmission.'
            );
        }

        $previousDate = null;
        foreach ($payments as $payment) {
            $date = Carbon::parse($payment->payment_date)->startOfDay();
            if ($previousDate !== null && $date->lessThanOrEqualTo($previousDate)) {
                throw new \InvalidArgumentException(
                    'Repayment dates must be unique and in ascending order before resubmission.'
                );
            }
            $previousDate = $date;
        }

        $lastBalance = $payments->last()?->outstanding_balance;
        if ($lastBalance !== null && abs((float) $lastBalance) > 0.01) {
            throw new \InvalidArgumentException(
                'The final schedule balance must be zero before resubmission.'
            );
        }
    }

    private function assertCorrectable(Loan $loan): void
    {
        if ($loan->status !== LoanApproval::STATUS_REJECTED) {
            throw new \InvalidArgumentException('Only a rejected loan schedule can be regenerated.');
        }

        $this->assertNoRepaymentActivity($loan);
    }

    private function assertNoRepaymentActivity(Loan $loan): void
    {
        if ($loan->transactions()->withTrashed()->exists()
            || $loan->payments()->where('total_paid', '>', 0.001)->exists()
            || $loan->payments()->whereNotNull('repayment_transaction_id')->exists()) {
            throw new \InvalidArgumentException(
                'This schedule cannot be replaced because repayment activity already exists.'
            );
        }
    }

    /** @return array<string, mixed> */
    private function scheduleInput(Loan $loan): array
    {
        return [
            'amount' => (float) $loan->amount,
            'interest_rate' => (float) $loan->interest_rate,
            'duration_months' => (int) $loan->duration_months,
            'repayment_method' => (string) $loan->repayment_method,
            'start_date' => (string) $loan->start_date,
            'currency' => (string) ($loan->currency ?? 'USD'),
            'admin_fee' => (float) ($loan->admin_fee ?? 0),
            'admin_fee_type' => (string) ($loan->admin_fee_type ?: 'one_time'),
            'pay_day_1' => $loan->pay_day_1,
            'pay_day_2' => $loan->pay_day_2,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $schedule
     * @return array<string, float|int|string>
     */
    private function validateGeneratedSchedule(Loan $loan, array $schedule): array
    {
        if ($schedule === []) {
            throw new \InvalidArgumentException('Schedule generation returned no installments.');
        }

        $principal = 0.0;
        $interest = 0.0;
        $fees = 0.0;
        $previousDate = null;

        foreach ($schedule as $index => $item) {
            foreach (['period', 'date', 'principal', 'interest'] as $required) {
                if (! array_key_exists($required, $item)) {
                    throw new \InvalidArgumentException("Generated installment {$index} is missing {$required}.");
                }
            }

            $date = Carbon::parse($this->normalizeDate((string) $item['date']))->startOfDay();
            if ($previousDate !== null && $date->lessThanOrEqualTo($previousDate)) {
                throw new \InvalidArgumentException('Generated repayment dates must be unique and ascending.');
            }
            $previousDate = $date;

            $principal += (float) $item['principal'];
            $interest += (float) $item['interest'];
            $fees += (float) ($item['fee'] ?? 0);
        }

        $principal = round($principal, 2);
        if (abs($principal - (float) $loan->amount) > 0.01) {
            throw new \InvalidArgumentException('Generated principal does not match the loan amount.');
        }

        $last = $schedule[array_key_last($schedule)];
        if (abs((float) ($last['balance'] ?? 0)) > 0.01) {
            throw new \InvalidArgumentException('Generated schedule does not close with a zero balance.');
        }

        return [
            'installments' => count($schedule),
            'principal' => $principal,
            'interest' => round($interest, 2),
            'fees' => round($fees, 2),
            'total' => round($principal + $interest + $fees, 2),
            'first_payment_date' => $this->normalizeDate((string) $schedule[0]['date']),
            'maturity_date' => $this->normalizeDate((string) $last['date']),
        ];
    }

    /** @return array<string, float|int> */
    private function persistedSummary(Loan $loan): array
    {
        return [
            'installments' => $loan->payments()->count(),
            'principal' => round((float) $loan->payments()->sum('principal_amount'), 2),
            'interest' => round((float) $loan->payments()->sum('interest_amount'), 2),
            'fees' => round((float) $loan->payments()->sum('fee_amount'), 2),
        ];
    }

    private function normalizeDate(string $date): string
    {
        if (preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $date)) {
            return Carbon::createFromFormat('d/m/Y', $date)->format('Y-m-d');
        }

        return Carbon::parse($date)->format('Y-m-d');
    }
}
