<?php

namespace App\Services;

use App\Models\Loan;
use App\Support\CurrencyRounding;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class LoanScheduleService
{
    public const SUPPORTED_METHODS = [
        'fixed_daily',
        'fixed_weekly',
        'fixed_biweekly',
        'fixed_monthly',
        'linear_monthly',
        'annuity_monthly',
        'Balloon',
        'negotiable',
    ];

    public function __construct(private readonly LoanCalculator $calculator)
    {
    }

    public static function canonicalPaymentFrequency(string $method, ?string $fallback = null): string
    {
        return match ($method) {
            'fixed_daily' => 'daily',
            'fixed_weekly' => 'weekly',
            'fixed_biweekly' => 'biweekly',
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
        $firstRepaymentDate = !empty($input['first_repayment_date'])
            ? (string) $input['first_repayment_date']
            : null;

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

            $schedule = array_map(static fn (array $item): array => [
                'period' => (int) ($item['payment_number'] ?? 0),
                'date' => (string) ($item['payment_date'] ?? ''),
                'principal' => (float) ($item['principal_amount'] ?? 0),
                'interest' => (float) ($item['interest_amount'] ?? 0),
                'fee' => (float) ($item['fee_amount'] ?? 0),
                'payment' => (float) ($item['total_paid'] ?? 0),
                'balance' => (float) ($item['remaining_balance'] ?? 0),
                'is_balloon' => (bool) ($item['is_balloon'] ?? false),
            ], $rawSchedule);

            return $this->normalize($schedule, $currency, $amount);
        }

        return $this->normalize($this->calculator->calculateLoanWithDates(
            $amount,
            $rate,
            $duration,
            $calculationMethod,
            $startDate,
            $currency,
            $adminFee,
            $adminFeeType,
            $payDay1,
            null,
            $firstRepaymentDate
        ), $currency, $amount);
    }

    /**
     * Normalize the backend schedule to the exact precision supported by the
     * payments table. Preview and persistence both consume these same rows.
     *
     * @param  array<int, array<string, mixed>>  $schedule
     * @return array<int, array<string, mixed>>
     */
    public function normalize(
        array $schedule,
        string $currency = 'USD',
        ?float $principalTarget = null
    ): array
    {
        $schedule = array_values($schedule);
        $principalTarget ??= round(array_sum(array_map(
            static fn (array $item): float => (float) ($item['principal'] ?? $item['principal_amount'] ?? 0),
            $schedule
        )), 2);
        $remainingPrincipal = round($principalTarget, 2);
        $lastIndex = count($schedule) - 1;

        return array_values(array_map(static function (array $item, int $index) use (
            $currency,
            &$remainingPrincipal,
            $lastIndex
        ): array {
            $rawPrincipal = (float) ($item['principal'] ?? $item['principal_amount'] ?? 0);
            $rawInterest = (float) ($item['interest'] ?? $item['interest_amount'] ?? 0);
            $rawFee = (float) ($item['fee'] ?? $item['fee_amount'] ?? 0);
            $usesWholeDollarRounding = stripos($currency, 'USD') !== false;

            $interest = $usesWholeDollarRounding
                ? CurrencyRounding::up($rawInterest, $currency)
                : round($rawInterest, 2);
            $fee = $usesWholeDollarRounding
                ? CurrencyRounding::up($rawFee, $currency)
                : round($rawFee, 2);

            if ($index === $lastIndex) {
                // The last installment absorbs accumulated rounding so total
                // principal remains exactly equal to the backend loan amount.
                $principal = round(max(0, $remainingPrincipal), 2);
            } else {
                $principal = $usesWholeDollarRounding
                    ? CurrencyRounding::up($rawPrincipal, $currency)
                    : round($rawPrincipal, 2);
                $principal = min($principal, $remainingPrincipal);
            }
            $remainingPrincipal = round(max(0, $remainingPrincipal - $principal), 2);

            $balance = $item['balance']
                ?? $item['remaining_balance']
                ?? $item['outstanding_balance']
                ?? null;

            return [
                ...$item,
                'period' => (int) ($item['period'] ?? 0),
                'date' => (string) ($item['date'] ?? ''),
                'principal' => $principal,
                'interest' => $interest,
                'fee' => $fee,
                'payment' => round($principal + $interest + $fee, 2),
                'balance' => $balance === null ? null : $remainingPrincipal,
            ];
        }, $schedule, array_keys($schedule)));
    }

    /**
     * Persist exactly the normalized schedule returned by the backend.
     *
     * @param  array<int, array<string, mixed>>  $schedule
     * @return Collection<int, \App\Models\Payment>
     */
    public function persist(Loan $loan, array $schedule): Collection
    {
        $schedule = $this->normalize($schedule, (string) ($loan->currency ?? 'USD'), (float) $loan->amount);
        if ($schedule === []) {
            throw new \RuntimeException('Schedule persistence failed: the backend returned no rows.');
        }

        if ($loan->payments()->exists()) {
            throw new \RuntimeException('Schedule persistence failed: this loan already has payment rows.');
        }

        foreach ($schedule as $item) {
            $loan->payments()->create([
                'payment_number' => $item['period'],
                'principal_amount' => $item['principal'],
                'interest_amount' => $item['interest'],
                'fee_amount' => $item['fee'],
                'outstanding_balance' => $item['balance'],
                'penalty_amount' => 0,
                'fee_paid' => 0,
                'total_paid' => 0,
                'payment_date' => $this->normalizeDate($item['date']),
                'payment_method' => 'Cash',
            ]);
        }

        $first = $schedule[0];
        $last = $schedule[array_key_last($schedule)];
        $loan->update([
            'monthly_payment' => $first['payment'],
            'maturity_date' => $this->normalizeDate($last['date']),
        ]);

        $payments = $loan->payments()->orderBy('payment_number')->get();
        $this->assertPersistedParity($schedule, $payments);

        return $payments;
    }

    private function normalizeDate(string $date): string
    {
        if (preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $date)) {
            return Carbon::createFromFormat('d/m/Y', $date)->format('Y-m-d');
        }

        return Carbon::parse($date)->format('Y-m-d');
    }

    /**
     * @param  array<int, array<string, mixed>>  $schedule
     * @param  Collection<int, \App\Models\Payment>  $payments
     */
    private function assertPersistedParity(array $schedule, Collection $payments): void
    {
        if ($payments->count() !== count($schedule)) {
            throw new \RuntimeException('Schedule persistence failed: row count differs from backend output.');
        }

        foreach ($schedule as $index => $item) {
            $payment = $payments->get($index);
            $storedTotal = round(
                (float) $payment->principal_amount
                + (float) $payment->interest_amount
                + (float) $payment->fee_amount,
                2
            );

            $matches = (int) $payment->payment_number === (int) $item['period']
                && (string) $payment->payment_date === $this->normalizeDate((string) $item['date'])
                && abs((float) $payment->principal_amount - (float) $item['principal']) < 0.001
                && abs((float) $payment->interest_amount - (float) $item['interest']) < 0.001
                && abs((float) $payment->fee_amount - (float) $item['fee']) < 0.001
                && abs($storedTotal - (float) $item['payment']) < 0.001;

            if ($item['balance'] === null) {
                $matches = $matches && $payment->outstanding_balance === null;
            } else {
                $matches = $matches
                    && abs((float) $payment->outstanding_balance - (float) $item['balance']) < 0.001;
            }

            if (! $matches) {
                throw new \RuntimeException(
                    'Schedule persistence failed: DB row '.($index + 1).' differs from backend output.'
                );
            }
        }
    }
}
