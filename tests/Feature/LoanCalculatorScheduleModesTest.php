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

    public function test_fixed_daily_smart_check_normalizes_a_short_final_payment(): void
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
        $this->assertEquals(44000, $payments[0]);
        $this->assertEquals(15500, $schedule[array_key_last($schedule)]['interest']);
    }
    public function test_fixed_monthly_smart_check_normalizes_a_short_final_payment(): void
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
        $this->assertEquals($schedule[1]['payment'], $schedule[$lastIndex]['payment']);
        $this->assertEquals(44000, $schedule[$lastIndex]['payment']);
        $this->assertEquals(15500, $schedule[$lastIndex]['interest']);
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
    public function test_fixed_weekly_smart_check_normalizes_a_short_final_payment(): void
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
        $this->assertEquals(44000, $payments[0]);
        $this->assertEquals(15500, $schedule[array_key_last($schedule)]['interest']);
    }
    public function test_fixed_biweekly_rate_and_smart_check_match_regular_payment(): void
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
        $this->assertEquals(44000, $payments[0]);
        $this->assertEquals(15500, $schedule[array_key_last($schedule)]['interest']);
    }

    public function test_biweekly_70_30_smart_check_matches_its_final_half_payment(): void
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

        $lastIndex = array_key_last($schedule);
        $this->assertEquals(34000, $schedule[$lastIndex - 1]['payment']);
        $this->assertEquals(20000, $schedule[$lastIndex]['payment']);
    }

    public function test_biweekly_50_50_smart_check_matches_its_final_half_payment(): void
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

        $lastIndex = array_key_last($schedule);
        $this->assertEquals(27000, $schedule[$lastIndex - 1]['payment']);
        $this->assertEquals(27000, $schedule[$lastIndex]['payment']);
    }
    public function test_biweekly_khr_principal_installments_are_rounded_to_thousands(): void
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
