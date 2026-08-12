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
        $this->assertSame('01/05/2026', $schedule[0]['date']);
        $this->assertSame('02/05/2026', $schedule[1]['date']);
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
    public function test_fixed_monthly_rounds_khr_principal_to_supported_units_and_closes_exactly(): void
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
        $this->assertEquals(43500, $schedule[1]['payment']);
        $this->assertEquals(38500, $schedule[$lastIndex]['payment']);
        $this->assertEquals(10000, $schedule[$lastIndex]['interest']);
        $this->assertEquals(0, $schedule[$lastIndex]['balance']);
        $this->assertEquals(1000000, array_sum(array_column($schedule, 'principal')));
    }

    public function test_all_monthly_khr_methods_avoid_sub_500_riel_tails(): void
    {
        $calculator = app(LoanCalculator::class);

        foreach (['fixed_monthly', 'annuity_monthly', 'linear_monthly', 'Balloon', 'negotiable'] as $method) {
            $schedule = $calculator->calculateLoanWithDates(
                1000000,
                16,
                12,
                $method,
                '2026-08-10',
                'KHR',
                0,
                'one_time',
                11
            );

            foreach ($schedule as $payment) {
                foreach (['principal', 'interest', 'payment', 'balance'] as $field) {
                    $this->assertSame(
                        0,
                        ((int) round($payment[$field])) % 500,
                        "{$method} {$field} contains a sub-500 riel tail"
                    );
                }
            }

            $this->assertEquals(1000000, array_sum(array_column($schedule, 'principal')), $method);
            $this->assertEquals(0, $schedule[array_key_last($schedule)]['balance'], $method);
        }
    }

    public function test_fixed_monthly_usd_principal_rounds_cents_up_and_closes_exactly(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            1000,
            1,
            3,
            'fixed_monthly',
            '2026-05-01',
            'USD'
        );

        $this->assertSame(334.0, $schedule[0]['principal']);
        $this->assertSame(334.0, $schedule[1]['principal']);
        $this->assertSame(332.0, $schedule[array_key_last($schedule)]['principal']);
        $this->assertSame(1000.0, array_sum(array_column($schedule, 'principal')));
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
        $this->assertSame('07/05/2026', $schedule[0]['date']);
        $this->assertSame('14/05/2026', $schedule[1]['date']);
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
        $this->assertSame('07/05/2026', $schedule[0]['date']);
        $this->assertSame('14/05/2026', $schedule[1]['date']);
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
        $this->assertSame('14/05/2026', $schedule[0]['date']);
        $this->assertSame('28/05/2026', $schedule[1]['date']);
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
        $this->assertEquals(23500, $schedule[0]['principal']);
        $this->assertEquals(11000, $schedule[0]['interest']);
        $this->assertSame('11/06/2026', $schedule[1]['date']);
        $this->assertEquals(23500, $schedule[1]['principal']);
        $this->assertEquals(7000, $schedule[1]['interest']);
        $this->assertEquals(326500, array_sum(array_column($schedule, 'interest')));
    }

    public function test_biweekly_70_30_first_repayment_uses_70_percent_plus_excess_day_interest(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            1000000,
            16,
            6,
            'fixed_15days_70_30',
            '2026-08-10',
            'KHR',
            0,
            'one_time',
            11,
            26
        );

        $this->assertSame('26/08/2026', $schedule[0]['date']);
        $this->assertEquals(117000, $schedule[0]['principal']);
        $this->assertEquals(123000, $schedule[0]['interest']);
        $this->assertEquals(240000, $schedule[0]['payment']);
        $this->assertEquals(883000, $schedule[0]['balance']);

        $this->assertSame('11/09/2026', $schedule[1]['date']);
        $this->assertEquals(117000, $schedule[1]['principal']);
        $this->assertEquals(112000, $schedule[1]['interest']);
        $this->assertEquals(229000, $schedule[1]['payment']);

        $this->assertSame('26/09/2026', $schedule[2]['date']);
        $this->assertEquals(50000, $schedule[2]['principal']);
        $this->assertEquals(48000, $schedule[2]['interest']);
        $this->assertEquals(98000, $schedule[2]['payment']);
    }

    public function test_biweekly_70_30_first_repayment_rounds_11_to_14_days_up_to_the_full_payment(): void
    {
        $calculator = app(LoanCalculator::class);
        $cases = [
            ['2026-08-11', 5, 20, '20/08/2026', 53500, 153500], // 10 inclusive days: actual daily interest
            ['2026-08-10', 5, 20, '20/08/2026', 112000, 212000], // 11 inclusive days: round to full
            ['2026-08-13', 11, 26, '26/08/2026', 112000, 212000], // 14 inclusive days: round to full
            ['2026-08-12', 11, 26, '26/08/2026', 112000, 212000], // 15 inclusive days: full
            ['2026-08-11', 11, 26, '26/08/2026', 117500, 217500], // 16 inclusive days: full + 1 day
        ];

        foreach ($cases as [$startDate, $payDay1, $payDay2, $expectedDate, $expectedInterest, $expectedPayment]) {
            $schedule = $calculator->calculateLoanWithDates(
                1000000,
                16,
                7,
                'fixed_15days_70_30',
                $startDate,
                'KHR',
                0,
                'one_time',
                $payDay1,
                $payDay2
            );

            $this->assertSame($expectedDate, $schedule[0]['date'], $startDate);
            $this->assertEquals(100000, $schedule[0]['principal'], $startDate);
            $this->assertEquals($expectedInterest, $schedule[0]['interest'], $startDate);
            $this->assertEquals($expectedPayment, $schedule[0]['payment'], $startDate);
        }
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
        $this->assertEquals(17000, $schedule[0]['principal']);
        $this->assertEquals(9000, $schedule[0]['interest']);
        $this->assertSame('11/06/2026', $schedule[1]['date']);
        $this->assertEquals(17000, $schedule[1]['principal']);
        $this->assertEquals(5000, $schedule[1]['interest']);
        $this->assertEquals(324000, array_sum(array_column($schedule, 'interest')));
    }

    public function test_biweekly_50_50_first_repayment_uses_half_the_monthly_amount_plus_excess_day_interest(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            1000000,
            16,
            6,
            'fixed_15days_50_50',
            '2026-08-10',
            'KHR',
            0,
            'one_time',
            11,
            26
        );

        $this->assertSame('26/08/2026', $schedule[0]['date']);
        $this->assertEquals(83500, $schedule[0]['principal']);
        $this->assertEquals(91000, $schedule[0]['interest']);
        $this->assertEquals(174500, $schedule[0]['payment']);
        $this->assertEquals(916500, $schedule[0]['balance']);
    }

    public function test_biweekly_50_50_first_repayment_applies_the_same_day_bands_as_70_30(): void
    {
        $calculator = app(LoanCalculator::class);
        $cases = [
            ['2026-08-11', 5, 20, '20/08/2026', 53500, 137000], // 10 inclusive days: actual daily interest
            ['2026-08-10', 5, 20, '20/08/2026', 80000, 163500], // 11 inclusive days: round to full
            ['2026-08-13', 11, 26, '26/08/2026', 80000, 163500], // 14 inclusive days: round to full
            ['2026-08-12', 11, 26, '26/08/2026', 80000, 163500], // 15 inclusive days: full
            ['2026-08-11', 11, 26, '26/08/2026', 85500, 169000], // 16 inclusive days: full + 1 day
        ];

        foreach ($cases as [$startDate, $payDay1, $payDay2, $expectedDate, $expectedInterest, $expectedPayment]) {
            $schedule = $calculator->calculateLoanWithDates(
                1000000,
                16,
                6,
                'fixed_15days_50_50',
                $startDate,
                'KHR',
                0,
                'one_time',
                $payDay1,
                $payDay2
            );

            $this->assertSame($expectedDate, $schedule[0]['date'], $startDate);
            $this->assertEquals(83500, $schedule[0]['principal'], $startDate);
            $this->assertEquals($expectedInterest, $schedule[0]['interest'], $startDate);
            $this->assertEquals($expectedPayment, $schedule[0]['payment'], $startDate);
        }
    }

    public function test_biweekly_split_methods_use_month_term_and_apply_their_first_repayment_rule(): void
    {
        $calculator = app(LoanCalculator::class);
        $expected = [
            'fixed_15days_70_30' => [58500, 58500, 161000],
            'fixed_15days_50_50' => [42000, 42000, 129500],
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

    public function test_biweekly_split_methods_keep_lane_payments_stable_and_preserve_totals(): void
    {
        $calculator = app(LoanCalculator::class);
        $expected = [
            'fixed_15days_70_30' => [
                'first_payment' => 261500,
                'second_payment' => 237500,
                'total_payment' => 2536000,
                'total_interest' => 1436000,
            ],
            'fixed_15days_50_50' => [
                'first_payment' => 194000,
                'second_payment' => 170000,
                'total_payment' => 2404000,
                'total_interest' => 1304000,
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
                $this->assertEquals(79000, $schedule[0]['principal']);
                $this->assertEquals(79000, $schedule[1]['principal']);
                $this->assertEquals(78500, $schedule[2]['principal']);
                $this->assertEquals(1021000, $schedule[0]['balance']);
                $this->assertEquals(863500, $schedule[2]['balance']);
                $this->assertEquals(78000, $schedule[array_key_last($schedule)]['principal']);
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

    public function test_biweekly_khr_principal_allocation_keeps_500_riel_units(): void
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
                $this->assertSame(0, ((int) $payment['principal']) % 500, $method);
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
