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

    public function test_fixed_daily_respects_an_explicit_first_repayment_date(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            300,
            3,
            3,
            'fixed_daily',
            '2026-05-01',
            'USD',
            0,
            'one_time',
            null,
            null,
            '2026-05-05'
        );

        $this->assertSame('05/05/2026', $schedule[0]['date']);
        $this->assertSame('06/05/2026', $schedule[1]['date']);
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

    public function test_fixed_daily_smart_check_equalizes_the_final_khr_payment(): void
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
        $this->assertEquals(43500, $payments[array_key_last($payments)]);
        $this->assertEquals(15000, $schedule[array_key_last($schedule)]['interest']);
        $this->assertEquals(1000000, array_sum(array_column($schedule, 'principal')));
        $this->assertEquals(0, $schedule[array_key_last($schedule)]['balance']);
    }

    public function test_fixed_daily_smart_check_equalizes_the_final_usd_payment(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            1000,
            2,
            6,
            'fixed_daily',
            '2026-05-01',
            'USD'
        );

        $payments = array_column($schedule, 'payment');
        $this->assertCount(1, array_unique($payments));
        $this->assertEquals(187, $payments[0]);
        $this->assertEquals(165, $schedule[array_key_last($schedule)]['principal']);
        $this->assertEquals(22, $schedule[array_key_last($schedule)]['interest']);
        $this->assertEquals(1000, array_sum(array_column($schedule, 'principal')));
        $this->assertEquals(0, $schedule[array_key_last($schedule)]['balance']);
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
        $this->assertEquals(43500, $schedule[$lastIndex]['payment']);
        $this->assertEquals(15000, $schedule[$lastIndex]['interest']);
        $this->assertEquals(0, $schedule[$lastIndex]['balance']);
        $this->assertEquals(1000000, array_sum(array_column($schedule, 'principal')));
    }

    public function test_annuity_monthly_smart_check_equalizes_a_higher_final_payment(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            1000000,
            16,
            6,
            'annuity_monthly',
            '2026-08-13',
            'USD',
            0,
            'one_time',
            11
        );

        $lastIndex = array_key_last($schedule);

        $this->assertEquals(271390, $schedule[$lastIndex - 1]['payment']);
        $this->assertEquals(37432, $schedule[$lastIndex]['interest']);
        $this->assertEquals(271390, $schedule[$lastIndex]['payment']);
        $this->assertEquals(1000000, array_sum(array_column($schedule, 'principal')));
        $this->assertEquals(0, $schedule[$lastIndex]['balance']);
    }

    public function test_annuity_monthly_usd_matches_the_regular_payment_in_the_final_month(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            3000,
            5.3,
            14,
            'annuity_monthly',
            '2026-08-13',
            'USD',
            0,
            'one_time',
            11
        );
        $lastIndex = array_key_last($schedule);

        $this->assertEquals(309, $schedule[$lastIndex - 1]['payment']);
        $this->assertEquals(298, $schedule[$lastIndex]['principal']);
        $this->assertEquals(11, $schedule[$lastIndex]['interest']);
        $this->assertEquals(309, $schedule[$lastIndex]['payment']);
        $this->assertEquals(3000, array_sum(array_column($schedule, 'principal')));
        $this->assertEquals(0, $schedule[$lastIndex]['balance']);
    }

    public function test_annuity_monthly_smart_check_uses_the_standard_payment_after_a_prorated_first_month(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            1000,
            10,
            2,
            'annuity_monthly',
            '2026-08-10',
            'USD',
            0,
            'one_time',
            11
        );

        $this->assertEquals(587, $schedule[0]['payment']);
        $this->assertEquals(577, $schedule[1]['payment']);
        $this->assertEquals(1000, array_sum(array_column($schedule, 'principal')));
        $this->assertEquals(0, $schedule[1]['balance']);
    }

    public function test_fixed_monthly_smart_check_does_not_copy_a_prorated_first_payment(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            1000,
            10,
            2,
            'fixed_monthly',
            '2026-08-10',
            'USD',
            0,
            'one_time',
            11
        );

        $this->assertEquals(610, $schedule[0]['payment']);
        $this->assertEquals(600, $schedule[1]['payment']);
        $this->assertEquals(1000, array_sum(array_column($schedule, 'principal')));
        $this->assertEquals(0, $schedule[1]['balance']);
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
        $this->assertSame(348.0, $schedule[0]['payment']);
        $this->assertSame(344.0, $schedule[array_key_last($schedule)]['payment']);
        $this->assertSame(1000.0, array_sum(array_column($schedule, 'principal')));
    }

    public function test_usd_monthly_equalizes_a_short_final_installment(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            3000,
            10,
            36,
            'fixed_monthly',
            '2026-08-13',
            'USD',
            0,
            'one_time',
            11
        );

        $lastIndex = array_key_last($schedule);

        $this->assertEquals(384, $schedule[$lastIndex - 1]['payment']);
        $this->assertEquals(60, $schedule[$lastIndex]['principal']);
        $this->assertEquals(324, $schedule[$lastIndex]['interest']);
        $this->assertEquals(384, $schedule[$lastIndex]['payment']);
        $this->assertEquals(3000, array_sum(array_column($schedule, 'principal')));
        $this->assertEquals(0, $schedule[$lastIndex]['balance']);
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
    public function test_fixed_weekly_smart_check_equalizes_the_final_khr_payment(): void
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
        $this->assertEquals(43500, $payments[array_key_last($payments)]);
        $this->assertEquals(15000, $schedule[array_key_last($schedule)]['interest']);
        $this->assertEquals(1000000, array_sum(array_column($schedule, 'principal')));
        $this->assertEquals(0, $schedule[array_key_last($schedule)]['balance']);
    }

    public function test_fixed_weekly_smart_check_equalizes_the_final_usd_payment(): void
    {
        $calculator = app(LoanCalculator::class);

        $schedule = $calculator->calculateLoanWithDates(
            1000,
            2,
            6,
            'fixed_weekly',
            '2026-05-01',
            'USD'
        );

        $payments = array_column($schedule, 'payment');
        $this->assertCount(1, array_unique($payments));
        $this->assertEquals(187, $payments[0]);
        $this->assertEquals(165, $schedule[array_key_last($schedule)]['principal']);
        $this->assertEquals(22, $schedule[array_key_last($schedule)]['interest']);
        $this->assertEquals(1000, array_sum(array_column($schedule, 'principal')));
        $this->assertEquals(0, $schedule[array_key_last($schedule)]['balance']);
    }
    public function test_fixed_biweekly_does_not_apply_monthly_smart_check(): void
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
        $this->assertNotSame($payments[0], $payments[array_key_last($payments)]);
        $this->assertEquals(43500, $payments[0]);
        $this->assertEquals(38500, $payments[array_key_last($payments)]);
        $this->assertEquals(10000, $schedule[array_key_last($schedule)]['interest']);
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
