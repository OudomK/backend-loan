<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\RepaymentTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RepaymentPreviewService
{
    /**
     * Build the backend-owned repayment preview at the selected transaction date.
     *
     * Historical entries are posted oldest-to-newest, so the current payment
     * balances represent the ledger immediately before this transaction.
     *
     * @return array<string, mixed>
     */
    public function build(Loan $loan, Carbon $transactionDate, string $repaymentType): array
    {
        $transactionDate = $transactionDate->copy()->startOfDay();
        $feeType = trim((string) ($loan->admin_fee_type ?? '')) ?: 'one_time';
        $usesInstallmentFee = $feeType === 'monthly';
        $dueExpression = $usesInstallmentFee
            ? 'total_paid < (principal_amount + interest_amount + COALESCE(fee_amount, 0))'
            : 'total_paid < (principal_amount + interest_amount)';

        $installments = Payment::query()
            ->where('loan_id', $loan->id)
            ->whereRaw($dueExpression)
            ->orderBy('payment_date')
            ->get();

        $feePaidSoFar = $usesInstallmentFee
            ? (float) RepaymentTransaction::query()
                ->where('loan_id', $loan->id)
                ->whereDate('transaction_date', '<=', $transactionDate->toDateString())
                ->sum('fee_paid')
            : 0.0;
        $totalFee = $usesInstallmentFee
            ? (float) $loan->amount * ((float) ($loan->admin_fee ?? 0) / 100)
            : 0.0;

        $timeline = $this->penaltyTimeline($loan, $transactionDate, $usesInstallmentFee);

        return [
            'installments' => $installments,
            'fee_type' => $feeType,
            'total_fee' => round($totalFee, 2),
            'fee_paid_so_far' => round($feePaidSoFar, 2),
            'accumulated_penalty' => round((float) ($loan->accumulated_penalty ?? 0), 2),
            'late_since_date' => $timeline['late_since_date'],
            'penalty_late_since_date' => $timeline['penalty_late_since_date'],
            'locked_aging' => $timeline['aging'],
            'aging' => $timeline['aging'],
            'penalty_due' => $timeline['penalty_due'],
            'penalty_rate' => $loan->resolvePenaltyRate(),
            'current_period_penalty_credits' => $timeline['penalty_credits'],
            'transaction_date' => $transactionDate->toDateString(),
            'repayment_type' => $repaymentType,
        ];
    }

    /**
     * Reconstruct late periods from immutable due/settlement dates. This avoids
     * charging today's aging while an operator is entering an old payment.
     *
     * @return array{late_since_date: ?string, penalty_late_since_date: ?string, aging: int, penalty_due: float, penalty_credits: float}
     */
    private function penaltyTimeline(Loan $loan, Carbon $referenceDate, bool $usesInstallmentFee): array
    {
        $intervals = [];
        $earliestOpenArrear = null;

        /** @var Collection<int, Payment> $payments */
        $payments = Payment::query()
            ->where('loan_id', $loan->id)
            ->orderBy('payment_date')
            ->get();

        foreach ($payments as $payment) {
            $dueDate = Carbon::parse($payment->payment_date)->startOfDay();
            if (! $dueDate->lt($referenceDate)) {
                continue;
            }

            $required = round(
                (float) $payment->principal_amount
                + (float) $payment->interest_amount
                + ($usesInstallmentFee ? (float) ($payment->fee_amount ?? 0) : 0),
                2
            );
            $isSettled = (float) $payment->total_paid >= $required - 0.01;
            $isOpen = ! $isSettled;

            if ($isOpen) {
                $endDate = $referenceDate->copy();
                $earliestOpenArrear ??= $dueDate->copy();
            } elseif ($payment->settled_at) {
                $endDate = Carbon::parse($payment->settled_at)->startOfDay();
                if ($endDate->gt($referenceDate)) {
                    $endDate = $referenceDate->copy();
                }
            } else {
                // A fully paid legacy row without reliable settlement timing
                // cannot prove that the customer was late.
                continue;
            }

            if (! $endDate->gt($dueDate)) {
                continue;
            }

            $intervals[] = [
                'start' => $dueDate,
                'end' => $endDate,
                'active' => $isOpen,
            ];
        }

        usort(
            $intervals,
            fn (array $left, array $right): int => strcmp(
                $left['start']->toDateString(),
                $right['start']->toDateString()
            )
        );
        $merged = [];
        foreach ($intervals as $interval) {
            $lastIndex = count($merged) - 1;
            if ($lastIndex < 0 || $interval['start']->gt($merged[$lastIndex]['end'])) {
                $merged[] = $interval;
                continue;
            }

            if ($interval['end']->gt($merged[$lastIndex]['end'])) {
                $merged[$lastIndex]['end'] = $interval['end'];
            }
            $merged[$lastIndex]['active'] = $merged[$lastIndex]['active'] || $interval['active'];
        }

        $totalLateDays = 0;
        $activePeriod = null;
        foreach ($merged as $period) {
            $days = (int) $period['start']->diffInDays($period['end']);
            $totalLateDays += $days;
            if ($period['active']) {
                $activePeriod = $period;
            }
        }

        $penaltyCredits = (float) RepaymentTransaction::query()
            ->where('loan_id', $loan->id)
            ->whereDate('transaction_date', '<=', $referenceDate->toDateString())
            ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(penalty_paid, 0) + COALESCE(waived_amount, 0)'));
        $timelinePenalty = max(
            0,
            $totalLateDays * $loan->resolvePenaltyRate() - $penaltyCredits
        );
        // Keep a genuine frozen legacy balance when settlement evidence is not
        // complete enough to reconstruct it from the timeline.
        $penaltyDue = max($timelinePenalty, (float) ($loan->accumulated_penalty ?? 0));

        $aging = 0;
        if ($activePeriod) {
            $aging = (int) $activePeriod['start']->diffInDays($referenceDate);
        } elseif ($penaltyDue > 0.01 && ! empty($merged)) {
            $lastPeriod = $merged[array_key_last($merged)];
            $aging = (int) $lastPeriod['start']->diffInDays($lastPeriod['end']);
        }

        return [
            'late_since_date' => $earliestOpenArrear?->toDateString(),
            'penalty_late_since_date' => $activePeriod ? $activePeriod['start']->toDateString() : null,
            'aging' => $aging,
            'penalty_due' => round($penaltyDue, 2),
            'penalty_credits' => round($penaltyCredits, 2),
        ];
    }
}
