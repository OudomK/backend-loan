<?php

namespace App\Services;

class LoanScheduleService
{
    public const SPLIT_METHODS = [
        'fixed_15days_70_30',
        'fixed_15days_50_50',
    ];

    public function __construct(private readonly LoanCalculator $calculator)
    {
    }

    public static function isSplitMethod(?string $method): bool
    {
        return in_array($method, self::SPLIT_METHODS, true);
    }

    public static function canonicalPaymentFrequency(string $method, ?string $fallback = null): string
    {
        return match ($method) {
            'fixed_daily' => 'daily',
            'fixed_weekly' => 'weekly',
            'fixed_biweekly', 'fixed_15days_70_30', 'fixed_15days_50_50' => 'biweekly',
            'negotiable' => 'term',
            'fixed_monthly', 'linear_monthly', 'annuity_monthly', 'Balloon' => 'monthly',
            default => strtolower(trim($fallback ?: 'monthly')),
        };
    }

    public static function displayPaymentFrequency(string $method, ?string $fallback = null): string
    {
        return match (self::canonicalPaymentFrequency($method, $fallback)) {
            'biweekly', 'bi-weekly' => 'Biweekly',
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'term' => 'Term',
            default => 'Monthly',
        };
    }

    /**
     * Convert the requested/disbursed amount into the principal used by the
     * schedule. Capitalized fees become principal; all other fee modes do not.
     */
    public function calculateSchedulePrincipal(
        float $requestedAmount,
        float $adminFeePercent,
        string $adminFeeType
    ): float {
        if ($adminFeeType !== 'capitalized_upfront' || $adminFeePercent <= 0) {
            return $requestedAmount;
        }

        return round($requestedAmount + (($requestedAmount * $adminFeePercent) / 100), 2);
    }

    /**
     * The single schedule-generation entry point used by Web, App preview,
     * App loan creation, and Admin Web loan creation.
     *
     * The supplied amount must already be the schedule principal (including a
     * capitalized fee, when applicable).
     */
    public function generate(array $input): array
    {
        $method = (string) ($input['repayment_method'] ?? 'fixed_monthly');
        $calculationMethod = $method === 'negotiable' ? 'fixed_monthly' : $method;
        $amount = (float) ($input['amount'] ?? 0);
        $rate = (float) ($input['interest_rate'] ?? 0);
        $duration = (int) ($input['duration_months'] ?? 0);
        $startDate = (string) ($input['start_date'] ?? '');
        $currency = (string) ($input['currency'] ?? 'USD');
        $adminFee = (float) ($input['admin_fee'] ?? 0);
        $adminFeeType = (string) ($input['admin_fee_type'] ?? 'one_time');
        $payDay1 = isset($input['pay_day_1']) ? (int) $input['pay_day_1'] : null;
        $payDay2 = isset($input['pay_day_2']) ? (int) $input['pay_day_2'] : null;
        $firstRepaymentDate = !empty($input['first_repayment_date'])
            ? (string) $input['first_repayment_date']
            : null;

        if (self::isSplitMethod($method)) {
            // These two products are contractually fixed to the 11th/26th.
            $payDay1 = 11;
            $payDay2 = 26;
            $firstRepaymentDate = null;
        }

        if ($calculationMethod === 'Balloon') {
            $rawSchedule = BalloonPaymentCalculator::generateSchedule(
                [
                    'amount' => $amount,
                    'interest_rate' => $rate,
                    'duration_months' => $duration,
                    'start_date' => $startDate,
                    'currency' => $currency,
                ],
                'interest_only',
                null,
                $payDay1,
                $adminFee,
                $adminFeeType,
                $firstRepaymentDate
            );

            return array_map(static fn (array $item): array => [
                'period' => (int) ($item['payment_number'] ?? 0),
                'date' => (string) ($item['payment_date'] ?? ''),
                'principal' => (float) ($item['principal_amount'] ?? 0),
                'interest' => (float) ($item['interest_amount'] ?? 0),
                'fee' => (float) ($item['fee_amount'] ?? 0),
                'payment' => (float) ($item['total_paid'] ?? 0),
                'balance' => (float) ($item['remaining_balance'] ?? 0),
                'is_balloon' => (bool) ($item['is_balloon'] ?? false),
            ], $rawSchedule);
        }

        return $this->calculator->calculateLoanWithDates(
            $amount,
            $rate,
            $duration,
            $calculationMethod,
            $startDate,
            $currency,
            $adminFee,
            $adminFeeType,
            $payDay1,
            $payDay2,
            $firstRepaymentDate
        );
    }
}
