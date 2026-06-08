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
