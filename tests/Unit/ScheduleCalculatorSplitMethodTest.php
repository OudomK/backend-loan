<?php

namespace Tests\Unit;

use App\Livewire\ScheduleCalculator;
use PHPUnit\Framework\TestCase;

class ScheduleCalculatorSplitMethodTest extends TestCase
{
    public function test_monthly_and_balloon_defaults_match_the_app(): void
    {
        $component = new ScheduleCalculator();
        $component->loan_date = '2026-08-08';

        $component->updatedRepaymentMethod('fixed_monthly');
        $this->assertSame('Monthly', $component->payment_frequency);
        $this->assertSame('2026-09-11', $component->first_repayment_date);

        $component->updatedRepaymentMethod('Balloon');
        $this->assertSame('Monthly', $component->payment_frequency);
        $this->assertSame('2026-09-08', $component->first_repayment_date);
    }

    public function test_daily_starts_next_day_while_other_intervals_keep_their_existing_dates(): void
    {
        $component = new ScheduleCalculator();
        $component->loan_date = '2026-08-10';

        $component->updatedRepaymentMethod('fixed_daily');
        $this->assertSame('2026-08-11', $component->first_repayment_date);

        $component->updatedRepaymentMethod('fixed_weekly');
        $this->assertSame('2026-08-16', $component->first_repayment_date);

        $component->updatedRepaymentMethod('fixed_biweekly');
        $this->assertSame('2026-08-23', $component->first_repayment_date);
    }
}
