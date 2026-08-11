<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RepaymentTransaction;
use Carbon\Carbon;

class PaymentSettlementTimingService
{
    private const TOLERANCE = 0.01;

    /**
     * Resolve the immutable timing snapshot for a payment schedule row.
     *
     * @return array{
     *     status: 'resolved'|'unsettled'|'unresolved',
     *     settled_at?: string,
     *     settled_due_date?: string,
     *     settled_days_variance?: int,
     *     settlement_source?: string
     * }
     */
    public function resolve(Payment $payment, bool $allowUpdatedAtFallback = false): array
    {
        $dueDate = Carbon::parse($payment->payment_date)->startOfDay();
        $requiredTotal = round(
            (float) $payment->principal_amount
            + (float) $payment->interest_amount
            + (float) ($payment->fee_amount ?? 0),
            2
        );
        $isFullyPaid = $requiredTotal > self::TOLERANCE
            && (float) $payment->total_paid >= ($requiredTotal - self::TOLERANCE);

        $allocations = PaymentAllocation::query()
            ->where('payment_id', $payment->id)
            ->with(['transaction' => fn ($query) => $query->withTrashed()])
            ->get()
            ->filter(fn (PaymentAllocation $allocation) => $allocation->transaction && ! $allocation->transaction->trashed())
            ->sort(function (PaymentAllocation $left, PaymentAllocation $right): int {
                $leftDate = (string) $left->transaction->transaction_date;
                $rightDate = (string) $right->transaction->transaction_date;
                $dateComparison = strcmp($leftDate, $rightDate);

                return $dateComparison !== 0
                    ? $dateComparison
                    : $left->id <=> $right->id;
            })
            ->values();

        $cumulativeApplied = 0.0;
        $lastInstallmentAllocationDate = null;

        foreach ($allocations as $allocation) {
            $transaction = $allocation->transaction;
            $transactionDate = Carbon::parse($transaction->transaction_date)->startOfDay();

            if ($transaction->repayment_type === 'Pay Off') {
                return $this->resolved($dueDate, $transactionDate, 'payoff');
            }

            $installmentApplied = round(
                (float) $allocation->fee_applied
                + (float) $allocation->interest_applied
                + (float) $allocation->principal_applied,
                2
            );

            // Some legacy allocations only populated amount_applied. Exclude the
            // penalty component because penalty alone does not settle an installment.
            if ($installmentApplied <= self::TOLERANCE) {
                $installmentApplied = round(max(
                    0,
                    (float) $allocation->amount_applied - (float) ($allocation->penalty_applied ?? 0)
                ), 2);
            }

            if ($installmentApplied <= self::TOLERANCE) {
                continue;
            }

            $cumulativeApplied = round($cumulativeApplied + $installmentApplied, 2);
            $lastInstallmentAllocationDate = $transactionDate;

            if ($requiredTotal > self::TOLERANCE
                && $cumulativeApplied >= ($requiredTotal - self::TOLERANCE)) {
                return $this->resolved($dueDate, $transactionDate, 'allocation');
            }
        }

        $directTransaction = null;
        if ($payment->repayment_transaction_id) {
            $directTransaction = RepaymentTransaction::withTrashed()
                ->find($payment->repayment_transaction_id);

            if ($directTransaction?->trashed()) {
                $directTransaction = null;
            }
        }

        if ($directTransaction?->repayment_type === 'Pay Off') {
            return $this->resolved(
                $dueDate,
                Carbon::parse($directTransaction->transaction_date)->startOfDay(),
                'payoff'
            );
        }

        if (! $isFullyPaid) {
            return ['status' => 'unsettled'];
        }

        if ($lastInstallmentAllocationDate) {
            return $this->resolved($dueDate, $lastInstallmentAllocationDate, 'allocation_inferred');
        }

        if ($directTransaction) {
            return $this->resolved(
                $dueDate,
                Carbon::parse($directTransaction->transaction_date)->startOfDay(),
                'transaction'
            );
        }

        if ($allowUpdatedAtFallback && $payment->updated_at) {
            return $this->resolved(
                $dueDate,
                Carbon::parse($payment->updated_at)->startOfDay(),
                'legacy_updated_at'
            );
        }

        return ['status' => 'unresolved'];
    }

    public function sync(Payment $payment, bool $allowUpdatedAtFallback = false): string
    {
        $result = $this->resolve($payment, $allowUpdatedAtFallback);

        if ($result['status'] === 'resolved') {
            $payment->forceFill([
                'settled_at' => $result['settled_at'],
                'settled_due_date' => $result['settled_due_date'],
                'settled_days_variance' => $result['settled_days_variance'],
                'settlement_source' => $result['settlement_source'],
            ])->saveQuietly();
        } elseif ($result['status'] === 'unsettled') {
            $payment->forceFill([
                'settled_at' => null,
                'settled_due_date' => null,
                'settled_days_variance' => null,
                'settlement_source' => null,
            ])->saveQuietly();
        }

        return $result['status'];
    }

    /**
     * @return array{
     *     status: 'resolved',
     *     settled_at: string,
     *     settled_due_date: string,
     *     settled_days_variance: int,
     *     settlement_source: string
     * }
     */
    private function resolved(Carbon $dueDate, Carbon $settledAt, string $source): array
    {
        $variance = 0;
        if ($settledAt->lt($dueDate)) {
            $variance = (int) abs($settledAt->diffInDays($dueDate));
        } elseif ($settledAt->gt($dueDate)) {
            $variance = -((int) abs($dueDate->diffInDays($settledAt)));
        }

        return [
            'status' => 'resolved',
            'settled_at' => $settledAt->toDateString(),
            'settled_due_date' => $dueDate->toDateString(),
            'settled_days_variance' => $variance,
            'settlement_source' => $source,
        ];
    }
}
