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
            1,
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
            1,
            'fixed_weekly',
            '2026-05-01',
            'USD'
        );

        $this->assertCount(5, $schedule);
        $this->assertSame('08/05/2026', $schedule[0]['date']);
        $this->assertSame('15/05/2026', $schedule[1]['date']);
        $this->assertEquals(0, $schedule[array_key_last($schedule)]['balance']);
    }
}
