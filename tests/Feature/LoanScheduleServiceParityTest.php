<?php

namespace Tests\Feature;

use App\Http\Controllers\LoanController;
use App\Livewire\ScheduleCalculator;
use App\Services\LoanScheduleService;
use Illuminate\Http\Request;
use Tests\TestCase;

class LoanScheduleServiceParityTest extends TestCase
{
    private function baseInput(string $method): array
    {
        return [
            'amount' => 1100000,
            'interest_rate' => 16.5,
            'duration_months' => 7,
            'repayment_method' => $method,
            'start_date' => '2026-08-08',
            'currency' => 'KHR',
            'admin_fee' => 0,
            'admin_fee_type' => 'one_time',
        ];
    }

    public function test_web_and_app_split_inputs_generate_identical_schedules(): void
    {
        $service = app(LoanScheduleService::class);

        foreach (LoanScheduleService::SPLIT_METHODS as $method) {
            $base = $this->baseInput($method);

            $webSchedule = $service->generate([
                ...$base,
                'first_repayment_date' => '2026-08-26',
            ]);
            $appSchedule = $service->generate([
                ...$base,
                'pay_day_1' => 11,
                'pay_day_2' => 26,
            ]);

            $this->assertSame($appSchedule, $webSchedule, $method);
            $this->assertCount(14, $webSchedule, $method);
            $this->assertSame('26/08/2026', $webSchedule[0]['date'], $method);
            $expectedTotal = $method === 'fixed_15days_70_30' ? 2536000 : 2404000;
            $this->assertEqualsWithDelta($expectedTotal, array_sum(array_column($webSchedule, 'payment')), 0.01, $method);
        }
    }

    public function test_web_calculator_component_uses_the_shared_schedule_service(): void
    {
        $component = new ScheduleCalculator();
        $component->amount = '1,100,000';
        $component->interest_rate = '16.5';
        $component->duration_months = '7';
        $component->loan_date = '2026-08-08';
        $component->currency = 'KHR';
        $component->repayment_method = 'fixed_15days_70_30';
        $component->first_repayment_date = '2026-08-26';

        $component->calculate();

        $expected = app(LoanScheduleService::class)->generate(
            $this->baseInput('fixed_15days_70_30')
        );

        $this->assertSame($expected, $component->schedule);
        $this->assertSame('Biweekly', $component->payment_frequency);
    }

    public function test_quick_web_form_and_app_api_match_for_every_visible_method_without_fees(): void
    {
        $methods = [
            'fixed_daily',
            'fixed_weekly',
            'fixed_15days_70_30',
            'fixed_15days_50_50',
            'fixed_monthly',
            'annuity_monthly',
            'linear_monthly',
            'Balloon',
            'negotiable',
        ];

        foreach ($methods as $method) {
            $web = new ScheduleCalculator();
            $web->amount = '1,100,000';
            $web->interest_rate = '16.5';
            $web->duration_months = '7';
            $web->loan_date = '2026-08-08';
            $web->currency = 'KHR';
            $web->repayment_method = $method;
            $web->updatedRepaymentMethod($method);
            $web->calculate();

            $appInput = $this->baseInput($method);

            if (LoanScheduleService::isSplitMethod($method)) {
                $appInput['pay_day_1'] = 11;
                $appInput['pay_day_2'] = 26;
            } elseif (in_array($method, ['fixed_monthly', 'annuity_monthly', 'linear_monthly'], true)) {
                $appInput['pay_day_1'] = 11;
            } elseif ($method === 'Balloon') {
                $appInput['pay_day_1'] = 8;
            }

            $request = Request::create('/api/loans/preview-schedule', 'POST', $appInput);
            $request->headers->set('Accept', 'application/json');
            $response = app(LoanController::class)->previewSchedule($request);
            $payload = $response->getData(true);

            $this->assertArrayHasKey('schedule', $payload, $method);
            $this->assertEquals($payload['schedule'], $web->schedule, $method);
        }
    }

    public function test_web_first_date_and_app_payment_day_generate_the_same_monthly_schedule(): void
    {
        $service = app(LoanScheduleService::class);
        $base = $this->baseInput('fixed_monthly');

        $webSchedule = $service->generate([
            ...$base,
            'first_repayment_date' => '2026-09-11',
        ]);
        $appSchedule = $service->generate([
            ...$base,
            'pay_day_1' => 11,
        ]);

        $this->assertSame($appSchedule, $webSchedule);
    }

    public function test_web_first_date_and_app_payment_day_generate_the_same_balloon_schedule(): void
    {
        $service = app(LoanScheduleService::class);
        $base = $this->baseInput('Balloon');

        $webSchedule = $service->generate([
            ...$base,
            'first_repayment_date' => '2026-09-08',
        ]);
        $appSchedule = $service->generate([
            ...$base,
            'pay_day_1' => 8,
        ]);

        $this->assertSame($appSchedule, $webSchedule);
    }

    public function test_negotiable_preview_uses_the_same_monthly_base_schedule_everywhere(): void
    {
        $service = app(LoanScheduleService::class);

        $negotiable = $service->generate($this->baseInput('negotiable'));
        $monthly = $service->generate($this->baseInput('fixed_monthly'));

        $this->assertSame($monthly, $negotiable);
    }

    public function test_app_api_preview_uses_the_shared_service_with_fees(): void
    {
        $service = app(LoanScheduleService::class);
        $requestedAmount = 1000000.0;
        $feePercent = 10.0;
        $feeType = 'capitalized_upfront';
        $schedulePrincipal = $service->calculateSchedulePrincipal($requestedAmount, $feePercent, $feeType);
        $input = [
            ...$this->baseInput('fixed_15days_70_30'),
            'amount' => $requestedAmount,
            'admin_fee' => $feePercent,
            'admin_fee_type' => $feeType,
            'pay_day_1' => 11,
            'pay_day_2' => 26,
        ];

        $request = Request::create('/api/loans/preview-schedule', 'POST', $input);
        $request->headers->set('Accept', 'application/json');
        $response = app(LoanController::class)->previewSchedule($request);
        $payload = $response->getData(true);

        $expected = $service->generate([
            ...$input,
            'amount' => $schedulePrincipal,
        ]);

        $this->assertSame(1100000.0, $schedulePrincipal);
        $this->assertEquals($expected, $payload['schedule']);
    }

    public function test_frequency_is_canonical_for_every_supported_method(): void
    {
        $this->assertSame('biweekly', LoanScheduleService::canonicalPaymentFrequency('fixed_15days_70_30', 'monthly'));
        $this->assertSame('biweekly', LoanScheduleService::canonicalPaymentFrequency('fixed_15days_50_50', 'monthly'));
        $this->assertSame('biweekly', LoanScheduleService::canonicalPaymentFrequency('fixed_biweekly', 'monthly'));
        $this->assertSame('weekly', LoanScheduleService::canonicalPaymentFrequency('fixed_weekly', 'monthly'));
        $this->assertSame('daily', LoanScheduleService::canonicalPaymentFrequency('fixed_daily', 'monthly'));
        $this->assertSame('monthly', LoanScheduleService::canonicalPaymentFrequency('fixed_monthly', 'biweekly'));
        $this->assertSame('term', LoanScheduleService::canonicalPaymentFrequency('negotiable', 'monthly'));
    }
}
