<?php

namespace Tests\Unit;

use App\Livewire\ScheduleCalculator;
use PHPUnit\Framework\TestCase;

class ScheduleCalculatorSplitMethodTest extends TestCase
{
    public function test_split_method_uses_biweekly_and_the_26th_for_early_month_disbursement(): void
    {
        $component = new ScheduleCalculator();
        $component->loan_date = '2026-08-08';

        $component->updatedRepaymentMethod('fixed_15days_70_30');

        $this->assertSame('Biweekly', $component->payment_frequency);
        $this->assertSame('2026-08-26', $component->first_repayment_date);
    }

    public function test_split_method_uses_the_11th_of_next_month_for_late_month_disbursement(): void
    {
        $component = new ScheduleCalculator();
        $component->loan_date = '2026-08-20';

        $component->updatedRepaymentMethod('fixed_15days_50_50');

        $this->assertSame('Biweekly', $component->payment_frequency);
        $this->assertSame('2026-09-11', $component->first_repayment_date);
    }

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
