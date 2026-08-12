<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Payment;
use App\Support\CurrencyRounding;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ManualLoanScheduleService
{
    /**
     * Preview a manual correction without writing anything to the database.
     *
     * @param  array<int, array<string, mixed>>  $changes
     * @return array{schedule: array<int, array<string, mixed>>, changes: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function preview(Loan $loan, array $changes): array
    {
        $this->assertLoanIsEditable($loan);

        $payments = $loan->payments()
            ->with('allocations')
            ->orderBy('payment_number')
            ->get();

        return $this->buildPreview($loan, $payments, $changes);
    }

    /**
     * Apply a manual correction and verify that every value persisted exactly.
     *
     * @param  array<int, array<string, mixed>>  $changes
     * @return array{schedule: array<int, array<string, mixed>>, changes: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function apply(
        Loan $loan,
        array $changes,
        string $reason,
        ?Authenticatable $actor = null
    ): array {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required for a schedule correction.',
            ]);
        }

        return DB::transaction(function () use ($loan, $changes, $reason, $actor): array {
            /** @var Loan $lockedLoan */
            $lockedLoan = Loan::whereKey($loan->getKey())->lockForUpdate()->firstOrFail();
            $this->assertLoanIsEditable($lockedLoan);

            $payments = $lockedLoan->payments()
                ->with('allocations')
                ->orderBy('payment_number')
                ->lockForUpdate()
                ->get();

            $preview = $this->buildPreview($lockedLoan, $payments, $changes);

            foreach ($preview['changes'] as $change) {
                $updates = [];
                foreach (['payment_date', 'principal_amount', 'interest_amount'] as $field) {
                    if ($change['old'][$field] !== $change['new'][$field]) {
                        $updates[$field] = $change['new'][$field];
                    }
                }

                if ($updates !== []) {
                    $payments->firstWhere('id', $change['id'])?->update($updates);
                }
            }

            $this->assertPersistedParity($lockedLoan, $preview['schedule']);

            if (Schema::hasTable('activity_log')) {
                $logger = activity('loan_schedule')
                    ->performedOn($lockedLoan)
                    ->withProperties([
                        'reason' => $reason,
                        'changes' => $preview['changes'],
                    ]);

                if ($actor !== null) {
                    $logger->causedBy($actor);
                }

                $logger->log('Repayment schedule manually corrected');
            }

            return $preview;
        });
    }

    private function assertLoanIsEditable(Loan $loan): void
    {
        if ($loan->status !== 'active') {
            throw ValidationException::withMessages([
                'loan' => 'Only an active loan cycle can have its schedule edited.',
            ]);
        }
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  array<int, array<string, mixed>>  $changes
     * @return array{schedule: array<int, array<string, mixed>>, changes: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function buildPreview(Loan $loan, Collection $payments, array $changes): array
    {
        if ($payments->isEmpty()) {
            throw ValidationException::withMessages([
                'payments' => 'This loan has no schedule rows to edit.',
            ]);
        }

        $currency = (string) ($loan->currency ?: 'USD');
        $requested = collect($changes)->keyBy(fn (array $row): int => (int) $row['id']);
        $knownIds = $payments->pluck('id')->map(fn ($id): int => (int) $id);
        $unknownIds = $requested->keys()->map(fn ($id): int => (int) $id)->diff($knownIds);
        if ($unknownIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'payments' => 'A selected schedule row does not belong to this loan.',
            ]);
        }

        $schedule = [];
        $diffs = [];

        foreach ($payments as $payment) {
            $change = $requested->get($payment->id, []);
            $hasPaymentActivity = (float) $payment->total_paid > 0.0001
                || (float) ($payment->prepayment ?? 0) > 0.0001
                || $payment->repayment_transaction_id !== null
                || $payment->allocations->isNotEmpty();

            if ($change !== [] && $hasPaymentActivity) {
                throw ValidationException::withMessages([
                    'payments' => "Installment {$payment->payment_number} already has payment activity and is read-only.",
                ]);
            }

            $old = [
                'payment_date' => Carbon::parse($payment->payment_date)->format('Y-m-d'),
                'principal_amount' => round((float) $payment->principal_amount, 2),
                'interest_amount' => round((float) $payment->interest_amount, 2),
            ];

            $date = array_key_exists('payment_date', $change)
                ? Carbon::parse((string) $change['payment_date'])->format('Y-m-d')
                : $old['payment_date'];
            $principal = array_key_exists('principal_amount', $change)
                ? CurrencyRounding::up((float) $change['principal_amount'], $currency)
                : $old['principal_amount'];
            $interest = array_key_exists('interest_amount', $change)
                ? CurrencyRounding::up((float) $change['interest_amount'], $currency)
                : $old['interest_amount'];
            $fee = round((float) ($payment->fee_amount ?? 0), 2);

            $row = [
                'id' => (int) $payment->id,
                'payment_number' => (int) $payment->payment_number,
                'payment_date' => $date,
                'principal_amount' => $principal,
                'interest_amount' => $interest,
                'fee_amount' => $fee,
                'total' => round($principal + $interest + $fee, 2),
                'outstanding_balance' => round((float) ($payment->outstanding_balance ?? 0), 2),
                'is_editable' => ! $hasPaymentActivity,
            ];
            $schedule[] = $row;

            $new = [
                'payment_date' => $date,
                'principal_amount' => $principal,
                'interest_amount' => $interest,
            ];
            if ($old !== $new) {
                $diffs[] = [
                    'id' => (int) $payment->id,
                    'payment_number' => (int) $payment->payment_number,
                    'old' => $old,
                    'new' => $new,
                ];
            }
        }

        if ($diffs === []) {
            throw ValidationException::withMessages([
                'payments' => 'No schedule changes were detected.',
            ]);
        }

        $dateWasEdited = collect($changes)->contains(
            fn (array $change): bool => array_key_exists('payment_date', $change)
        );
        if ($dateWasEdited) {
            $previousDate = null;
            $loanStartDate = $loan->start_date
                ? Carbon::parse($loan->start_date)->startOfDay()
                : null;
            foreach ($schedule as $index => $row) {
                $date = Carbon::parse($row['payment_date'])->startOfDay();
                if ($index === 0 && $loanStartDate !== null && $date->lt($loanStartDate)) {
                    throw ValidationException::withMessages([
                        'payments' => 'The first payment date cannot be before the loan start date.',
                    ]);
                }
                if ($previousDate !== null && ! $date->gt($previousDate)) {
                    throw ValidationException::withMessages([
                        'payments' => 'Payment dates must be unique and in ascending installment order.',
                    ]);
                }
                $previousDate = $date;
            }
        }

        return [
            'schedule' => $schedule,
            'changes' => $diffs,
            'summary' => [
                'currency' => $currency,
                'loan_amount' => round((float) $loan->amount, 2),
                'principal_total' => round((float) collect($schedule)->sum('principal_amount'), 2),
                'interest_total' => round((float) collect($schedule)->sum('interest_amount'), 2),
                'payment_total' => round((float) collect($schedule)->sum('total'), 2),
                'changed_rows' => count($diffs),
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $expected */
    private function assertPersistedParity(Loan $loan, array $expected): void
    {
        $actual = $loan->payments()->orderBy('payment_number')->get();
        if ($actual->count() !== count($expected)) {
            throw new \RuntimeException('Schedule correction failed: DB row count differs from the preview.');
        }

        foreach ($actual as $index => $payment) {
            $row = $expected[$index];
            $matches = (int) $payment->id === (int) $row['id']
                && Carbon::parse($payment->payment_date)->format('Y-m-d') === $row['payment_date']
                && abs((float) $payment->principal_amount - (float) $row['principal_amount']) < 0.001
                && abs((float) $payment->interest_amount - (float) $row['interest_amount']) < 0.001
                && abs((float) $payment->outstanding_balance - (float) $row['outstanding_balance']) < 0.001;

            if (! $matches) {
                throw new \RuntimeException(
                    'Schedule correction failed: DB installment '.($index + 1).' differs from the preview.'
                );
            }
        }
    }
}
