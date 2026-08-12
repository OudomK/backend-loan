<?php

namespace Tests\Unit;

use App\Support\CurrencyRounding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CurrencyRoundingTest extends TestCase
{
    public static function khrAmounts(): array
    {
        return [
            'already on 1000' => [1200000, 1200000],
            'round small remainder upward' => [1200100, 1200500],
            'round 400 upward' => [1200400, 1200500],
            'already on 500' => [1200500, 1200500],
            'round 600 upward' => [1200600, 1201000],
            'round 900 upward' => [1200900, 1201000],
            'round 999 upward' => [1200999, 1201000],
        ];
    }

    #[DataProvider('khrAmounts')]
    public function test_khr_amounts_always_round_up_to_a_500_riel_unit(float $amount, float $expected): void
    {
        $this->assertSame($expected, CurrencyRounding::up($amount, 'KHR'));
    }

    public function test_usd_amounts_round_up_to_a_whole_dollar(): void
    {
        $this->assertSame(1201.0, CurrencyRounding::up(1200.01, 'USD'));
        $this->assertSame(1201.0, CurrencyRounding::up(1200.99, 'USD'));
        $this->assertSame(1200.0, CurrencyRounding::up(1200, 'USD'));
    }

    #[DataProvider('khrAmounts')]
    public function test_all_khr_schedule_rounding_uses_the_same_upward_500_riel_bands(float $amount, float $expected): void
    {
        $this->assertSame($expected, CurrencyRounding::standard($amount, 'KHR'));
        $this->assertSame($expected, CurrencyRounding::cumulativePrincipal($amount, 'KHR'));
    }

}
