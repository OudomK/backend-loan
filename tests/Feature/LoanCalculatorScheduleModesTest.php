<?php

namespace Tests\Feature;

use App\Services\LoanCalculator;
use Tests\TestCase;

class LoanCalculatorScheduleModesTest extends TestCase
{
    public function test_fixed_daily_schedule_generates_daily_installments(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            300,
            3,
            30,
            'fixed_daily',
            '2026-05-01',
            'USD'
        );

        $this->assertCount(30, $schedule);
        $this->assertSame('02/05/2026', $schedule[0]['date']);
        $this->assertSame('03/05/2026', $schedule[1]['date']);
        $this->assertEquals(0, $schedule[array_key_last($schedule)]['balance']);
    }

    public function test_fixed_daily_rate_is_charged_per_payment(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            1000000,
            1,
            4,
            'fixed_daily',
            '2026-05-01',
            'KHR'
        );

        $this->assertEquals(10000, $schedule[0]['interest']);
        $this->assertEquals(40000, array_sum(array_column($schedule, 'interest')));
    }

    public function test_fixed_daily_smart_check_normalizes_the_final_payment(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            1000000,
            1,
            30,
            'fixed_daily',
            '2026-05-01',
            'KHR'
        );

        $payments = array_column($schedule, 'payment');
        $this->assertSame($payments[0], $payments[array_key_last($payments)]);
        $this->assertEquals(43500, $payments[0]);
        $this->assertEquals(15000, $schedule[array_key_last($schedule)]['interest']);
    }
    public function test_fixed_monthly_keeps_exact_principal_and_rounds_only_interest(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            1000000,
            1,
            30,
            'fixed_monthly',
            '2026-05-01',
            'KHR'
        );

        $lastIndex = array_key_last($schedule);
        $this->assertEqualsWithDelta(43333.33, $schedule[1]['payment'], 0.01);
        $this->assertEqualsWithDelta(43333.43, $schedule[$lastIndex]['payment'], 0.01);
        $this->assertEquals(10000, $schedule[$lastIndex]['interest']);
    }
    public function test_fixed_weekly_schedule_generates_weekly_installments(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            600,
            4,
            5,
            'fixed_weekly',
            '2026-05-01',
            'USD'
        );

        $this->assertCount(5, $schedule);
        $this->assertSame('08/05/2026', $schedule[0]['date']);
        $this->assertSame('15/05/2026', $schedule[1]['date']);
        $this->assertEquals(0, $schedule[array_key_last($schedule)]['balance']);
    }

    public function test_fixed_weekly_rate_is_charged_per_payment(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            1000000,
            1,
            4,
            'fixed_weekly',
            '2026-05-01',
            'KHR'
        );

        $this->assertEquals(10000, $schedule[0]['interest']);
        $this->assertEquals(40000, array_sum(array_column($schedule, 'interest')));
    }
    public function test_fixed_weekly_smart_check_normalizes_the_final_payment(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            1000000,
            1,
            30,
            'fixed_weekly',
            '2026-05-01',
            'KHR'
        );

        $payments = array_column($schedule, 'payment');
        $this->assertSame($payments[0], $payments[array_key_last($payments)]);
        $this->assertEquals(43500, $payments[0]);
        $this->assertEquals(15000, $schedule[array_key_last($schedule)]['interest']);
    }
    public function test_fixed_biweekly_smart_check_normalizes_the_final_payment(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            1000000,
            1,
            30,
            'fixed_biweekly',
            '2026-05-01',
            'KHR'
        );

        $payments = array_column($schedule, 'payment');
        $this->assertSame($payments[0], $payments[array_key_last($payments)]);
        $this->assertEquals(43500, $payments[0]);
        $this->assertEquals(15000, $schedule[array_key_last($schedule)]['interest']);
    }

    public function test_biweekly_70_30_preserves_split_term_and_period_interest(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            1000000,
            1,
            30,
            'fixed_15days_70_30',
            '2026-05-01',
            'KHR'
        );

        $this->assertCount(60, $schedule);
        $this->assertSame('26/05/2026', $schedule[0]['date']);
        $this->assertEquals(29000, $schedule[0]['principal']);
        $this->assertEquals(3000, $schedule[0]['interest']);
        $this->assertSame('11/06/2026', $schedule[1]['date']);
        $this->assertEquals(24000, $schedule[1]['principal']);
        $this->assertEquals(7000, $schedule[1]['interest']);
        $this->assertEquals(339000, array_sum(array_column($schedule, 'interest')));
    }

    public function test_biweekly_50_50_preserves_split_term_and_period_interest(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            1000000,
            1,
            30,
            'fixed_15days_50_50',
            '2026-05-01',
            'KHR'
        );

        $this->assertCount(60, $schedule);
        $this->assertSame('26/05/2026', $schedule[0]['date']);
        $this->assertEquals(29000, $schedule[0]['principal']);
        $this->assertEquals(5000, $schedule[0]['interest']);
        $this->assertSame('11/06/2026', $schedule[1]['date']);
        $this->assertEquals(17000, $schedule[1]['principal']);
        $this->assertEquals(5000, $schedule[1]['interest']);
        $this->assertEquals(332000, array_sum(array_column($schedule, 'interest')));
    }

    public function test_biweekly_split_methods_use_month_term_and_prorate_first_principal(): void
    {
        $calculator = app(LoanCalculator::class);
        $expected = [
            'fixed_15days_70_30' => [53000, 59000, 156000],
            'fixed_15days_50_50' => [53000, 42000, 139000],
        ];

        foreach ($expected as $method => [$firstPrincipal, $secondPrincipal, $totalInterest]) {
            $schedule = $calculator->calculateLoanWithDates(
                1000000,
                1,
                12,
                $method,
                '2026-08-08',
                'KHR',
                0,
                'one_time',
                11,
                26
            );

            $this->assertCount(24, $schedule, $method);
            $this->assertSame('26/08/2026', $schedule[0]['date'], $method);
            $this->assertEqualsWithDelta($firstPrincipal, $schedule[0]['principal'], 0.01, $method);
            $this->assertSame('11/09/2026', $schedule[1]['date'], $method);
            $this->assertEqualsWithDelta($secondPrincipal, $schedule[1]['principal'], 0.01, $method);
            $this->assertEquals($totalInterest, array_sum(array_column($schedule, 'interest')), $method);
        }
    }

    public function test_biweekly_split_methods_split_one_monthly_total_instead_of_doubling_interest(): void
    {
        $calculator = app(LoanCalculator::class);
        $expected = [
            'fixed_15days_70_30' => [
                'first_payment' => 154500,
                'second_payment' => 237500,
                'total_payment' => 2432000,
                'total_interest' => 1332000,
            ],
            'fixed_15days_50_50' => [
                'first_payment' => 191000,
                'second_payment' => 170000,
                'total_payment' => 2401000,
                'total_interest' => 1301000,
            ],
        ];

        foreach ($expected as $method => $amounts) {
            $schedule = $calculator->calculateLoanWithDates(
                1100000,
                16.5,
                7,
                $method,
                '2026-08-08',
                'KHR',
                0,
                'one_time',
                11,
                26
            );

            $this->assertCount(14, $schedule, $method);
            $this->assertEqualsWithDelta($amounts['first_payment'], $schedule[0]['payment'], 0.01, $method);
            $this->assertEqualsWithDelta($amounts['second_payment'], $schedule[1]['payment'], 0.01, $method);
            $this->assertEqualsWithDelta(1100000, array_sum(array_column($schedule, 'principal')), 0.01, $method);
            $this->assertEquals($amounts['total_interest'], array_sum(array_column($schedule, 'interest')), $method);
            $this->assertEqualsWithDelta($amounts['total_payment'], array_sum(array_column($schedule, 'payment')), 0.01, $method);
            $this->assertEquals(0, $schedule[array_key_last($schedule)]['balance'], $method);

            $paymentsByDay = [];
            foreach (array_slice($schedule, 1) as $payment) {
                $day = substr($payment['date'], 0, 2);
                $paymentsByDay[$day][] = $payment['payment'];
            }
            foreach ($paymentsByDay as $payments) {
                $this->assertCount(1, array_unique($payments), $method);
            }

            if ($method === 'fixed_15days_50_50') {
                $this->assertEquals(100000, $schedule[0]['principal']);
                $this->assertEquals(79000, $schedule[1]['principal']);
                $this->assertEquals(79000, $schedule[2]['principal']);
                $this->assertEquals(1000000, $schedule[0]['balance']);
                $this->assertEquals(842000, $schedule[2]['balance']);
                $this->assertEquals(57000, $schedule[array_key_last($schedule)]['principal']);
                $this->assertEquals(170000, $schedule[array_key_last($schedule)]['payment']);
            }
        }
    }

    public function test_split_methods_keep_the_biweekly_label_with_a_monthly_rate(): void
    {
        foreach (['fixed_15days_70_30', 'fixed_15days_50_50'] as $method) {
            $frequency = \App\Support\FormatHelper::effectivePaymentFrequency('biweekly', $method);

            $this->assertSame('biweekly', $frequency);
            $this->assertEquals(16.5, \App\Support\FormatHelper::calculateMonthlyRate(16.5, $frequency));
        }
    }

    public function test_biweekly_khr_principal_allocation_keeps_thousand_riel_units(): void
    {
        $calculator = app(LoanCalculator::class);

        foreach (['fixed_15days_70_30', 'fixed_15days_50_50'] as $method) {
            $schedule = $calculator->calculateLoanWithDates(
                1000000,
                1,
                30,
                $method,
                '2026-05-01',
                'KHR'
            );

            foreach ($schedule as $payment) {
                $this->assertSame(0, ((int) $payment['principal']) % 1000, $method);
            }
        }
    }
    public function test_supported_schedule_modes_preserve_principal_total_and_close_balance(): void
    {
        $calculator = app(LoanCalculator::class);
        $methods = [
            'fixed_daily',
            'fixed_weekly',
            'fixed_monthly',
            'annuity_monthly',
            'linear_monthly',
            'Balloon',
            'negotiable',
            'fixed_15days_70_30',
            'fixed_15days_50_50',
        ];
        $cases = [
            ['principal' => 25000, 'duration' => 1, 'currency' => 'KHR'],
            ['principal' => 1000000, 'duration' => 12, 'currency' => 'KHR'],
            ['principal' => 1000, 'duration' => 12, 'currency' => 'USD'],
        ];

        foreach ($cases as $case) {
            foreach ($methods as $method) {
                $schedule = $calculator->calculateLoanWithDates(
                    $case['principal'],
                    3,
                    $case['duration'],
                    $method,
                    '2026-06-01',
                    $case['currency']
                );

                $this->assertNotEmpty($schedule, "Schedule is empty for {$method}");
                $principalSum = array_sum(array_column($schedule, 'principal'));
                $lastBalance = $schedule[array_key_last($schedule)]['balance'];

                $message = "{$method} {$case['currency']} {$case['principal']} duration {$case['duration']}";
                $this->assertEqualsWithDelta($case['principal'], $principalSum, 0.0001, $message);
                $this->assertEqualsWithDelta(0, $lastBalance, 0.0001, $message);

                foreach ($schedule as $row) {
                    $this->assertGreaterThanOrEqual(0, $row['principal'], $message);
                    $this->assertGreaterThanOrEqual(0, $row['balance'], $message);
                }
            }
        }
    }
}
