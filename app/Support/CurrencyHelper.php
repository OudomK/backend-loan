<?php

namespace App\Support;

final class CurrencyHelper
{
    public const USD = 'USD';

    public const KHR = 'KHR';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::USD => self::USD,
            self::KHR => self::KHR,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allowed(): array
    {
        return array_keys(self::options());
    }

    public static function isSupported(mixed $currency): bool
    {
        $value = strtoupper(trim((string) $currency));

        return $value !== ''
            && (
                str_contains($value, self::USD)
                || str_contains($value, self::KHR)
                || str_contains($value, '$')
                || str_contains($value, '៛')
            );
    }

    public static function normalize(mixed $currency, string $default = self::USD): string
    {
        $value = strtoupper(trim((string) $currency));

        if ($value === '') {
            return $default;
        }

        if (str_contains($value, self::KHR) || str_contains($value, '៛')) {
            return self::KHR;
        }

        if (str_contains($value, self::USD) || str_contains($value, '$')) {
            return self::USD;
        }

        return in_array($value, self::allowed(), true) ? $value : $default;
    }

    public static function symbol(mixed $currency): string
    {
        return self::normalize($currency) === self::KHR ? '៛' : '$';
    }

    public static function format(mixed $amount, mixed $currency, bool $includeCode = true, ?int $decimals = null): string
    {
        $normalized = self::normalize($currency);
        $formatted = number_format((float) $amount, $decimals ?? self::decimals($normalized), '.', ',');

        if (! $includeCode) {
            return $formatted;
        }

        return $normalized . ' ' . $formatted;
    }

    public static function decimals(mixed $currency): int
    {
        return self::normalize($currency) === self::KHR ? 0 : 2;
    }
}
