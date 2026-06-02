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

    /**
     * Admin/UI display: USD "$ 1,000,000", KHR "1,000,000 ៛".
     */
    public static function display(mixed $amount, mixed $currency, ?int $decimals = null): string
    {
        $normalized = self::normalize($currency);
        $formatted = number_format(
            (float) $amount,
            $decimals ?? self::decimals($normalized),
            '.',
            ',',
        );

        if ($normalized === self::KHR) {
            return $formatted . ' ' . self::symbol(self::KHR);
        }

        return self::symbol(self::USD) . ' ' . $formatted;
    }

    /**
     * Dual-currency stat line for Filament dashboard cards.
     */
    public static function displayDual(
        float $usd,
        float $khr,
        ?int $usdDecimals = 0,
        ?int $khrDecimals = 0,
        string $secondaryClass = 'text-sm font-normal text-gray-500',
    ): string {
        $hasUsd = abs($usd) > 0.0001;
        $hasKhr = abs($khr) > 0.0001;

        if ($hasUsd && $hasKhr) {
            return self::display($usd, self::USD, $usdDecimals)
                . ' <span class="' . $secondaryClass . '">| '
                . self::display($khr, self::KHR, $khrDecimals)
                . '</span>';
        }

        if ($hasKhr) {
            return self::display($khr, self::KHR, $khrDecimals);
        }

        return self::display($usd, self::USD, $usdDecimals);
    }

    /**
     * Dual-currency text without HTML wrappers (for use inside existing markup).
     */
    public static function displayDualPlain(
        float $usd,
        float $khr,
        ?int $usdDecimals = 0,
        ?int $khrDecimals = 0,
    ): string {
        $parts = [];

        if (abs($usd) > 0.0001) {
            $parts[] = self::display($usd, self::USD, $usdDecimals);
        }

        if (abs($khr) > 0.0001) {
            $parts[] = self::display($khr, self::KHR, $khrDecimals);
        }

        if ($parts === []) {
            return self::display(0, self::USD, $usdDecimals);
        }

        return implode(' | ', $parts);
    }

    public static function decimals(mixed $currency): int
    {
        return self::normalize($currency) === self::KHR ? 0 : 2;
    }
}
