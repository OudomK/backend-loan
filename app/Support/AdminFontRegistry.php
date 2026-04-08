<?php

namespace App\Support;

class AdminFontRegistry
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'kantumruy_pro' => 'Kantumruy Pro',
            'krasar' => 'Krasar',
            'khmer_os_system' => 'Khmer OS System',
            'khmer_ui' => 'Khmer UI',
            'segoe_ui' => 'Segoe UI',
        ];
    }

    public static function defaultKey(): string
    {
        return 'kantumruy_pro';
    }

    public static function count(): int
    {
        return count(self::options());
    }

    public static function labelsAsText(): string
    {
        return implode(', ', array_values(self::options()));
    }

    public static function resolveKey(?string $key): string
    {
        if ($key !== null && array_key_exists($key, self::options())) {
            return $key;
        }

        return self::defaultKey();
    }

    public static function cssStack(?string $key): string
    {
        return match (self::resolveKey($key)) {
            'krasar' => "'Krasar', 'Kantumruy Pro', 'Khmer OS System', 'Khmer UI', 'Segoe UI', sans-serif",
            'khmer_os_system' => "'Khmer OS System', 'Khmer UI', 'Segoe UI', sans-serif",
            'khmer_ui' => "'Khmer UI', 'Segoe UI', sans-serif",
            'segoe_ui' => "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif",
            default => "'Kantumruy Pro', 'Khmer OS System', 'Khmer UI', 'Segoe UI', sans-serif",
        };
    }
}
