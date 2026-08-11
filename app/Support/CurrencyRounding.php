<?php

namespace App\Support;

final class CurrencyRounding
{
    /**
     * Round repayment amounts upward to the smallest supported currency unit.
     * KHR schedules use 500-riel units; USD schedules use whole-dollar units.
     */
    public static function up(float $amount, string $currency): float
    {
        if ($amount <= 0) {
            return 0;
        }

        if (stripos($currency, 'KHR') !== false) {
            return (float) (ceil($amount / 500) * 500);
        }

        return (float) ceil($amount);
    }

    public static function standard(float $amount, string $currency): float
    {
        return self::up($amount, $currency);
    }

    public static function cumulativePrincipal(float $amount, string $currency): float
    {
        return self::up($amount, $currency);
    }

}
