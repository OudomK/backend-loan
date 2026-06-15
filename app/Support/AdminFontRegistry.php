<?php

namespace App\Support;

use App\Models\CustomFont;
use Illuminate\Support\Facades\Schema;

class AdminFontRegistry
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return self::coreOptions() + self::activeCustomOptions();
    }

    /**
     * @return array<string, string>
     */
    public static function coreOptions(): array
    {
        return [
            'kantumruy_pro' => 'Kantumruy Pro',
            'krasar' => 'Krasar',
            'khmer_os_battambang' => 'Khmer OS Battambang',
            'khmer_os_siemreap' => 'Khmer OS Siemreap',
            'khmer_os_system' => 'Khmer OS System',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function activeCustomOptions(): array
    {
        try {
            if (!Schema::hasTable('custom_fonts')) {
                return [];
            }

            $query = CustomFont::query()
                ->where('is_active', true)
                ->orderBy('name');

            if (Schema::hasColumn('custom_fonts', 'is_system')) {
                $query->where('is_system', false);
            }

            return $query->pluck('name', 'key')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public static function syncCoreFontsToDatabase(): void
    {
        try {
            if (
                !Schema::hasTable('custom_fonts') ||
                !Schema::hasColumn('custom_fonts', 'is_system')
            ) {
                return;
            }

            foreach (self::coreOptions() as $key => $name) {
                CustomFont::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'name' => $name,
                        'file_path' => "system-fonts/{$key}",
                        'is_system' => true,
                        'is_active' => true,
                    ],
                );
            }
        } catch (\Throwable) {
            //
        }
    }

    public static function defaultKey(): string
    {
        return 'kantumruy_pro';
    }

    public static function count(): int
    {
        return count(self::options());
    }

    public static function coreCount(): int
    {
        return count(self::coreOptions());
    }

    public static function activeCustomCount(): int
    {
        return count(self::activeCustomOptions());
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
        $resolvedKey = self::resolveKey($key);

        if ($customFont = self::activeCustomOptions()[$resolvedKey] ?? null) {
            return self::fontStack($customFont);
        }

        return match ($resolvedKey) {
            'kantumruy_pro' => self::fontStack('Kantumruy Pro'),
            'krasar' => self::fontStack('Krasar'),
            'khmer_os_battambang' => self::fontStack('Khmer OS Battambang'),
            'khmer_os_siemreap' => self::fontStack('Khmer OS Siemreap'),
            'khmer_os_system' => self::fontStack('Khmer OS System'),
            default => self::fontStack('Kantumruy Pro'),
        };
    }

    private static function fontStack(string $fontFamily): string
    {
        if ($fontFamily === '') {
            return "'Kantumruy Pro', 'Khmer OS System', sans-serif";
        }

        $escapedFontFamily = str_replace("'", "\\'", $fontFamily);

        return "'{$escapedFontFamily}', 'Kantumruy Pro', 'Khmer OS System', sans-serif";
    }
}
