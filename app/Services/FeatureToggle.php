<?php

namespace App\Services;

use App\Models\Setting;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;

class FeatureToggle
{
    /**
     * Check if a feature is enabled.
     * Features are enabled by default unless explicitly turned off.
     */
    public static function isEnabled(string $featureKey): bool
    {
        $cacheKey = "feature_toggle_{$featureKey}";

        return Cache::rememberForever($cacheKey, function () use ($featureKey) {
            $setting = Setting::where('key', 'feature_' . $featureKey)->first();

            if (!$setting) {
                return true; // Default to true if not set
            }

            return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
        });
    }

    /**
     * Check whether the feature should be accessible for a specific user.
     * Super Admin always bypasses feature toggles.
     */
    public static function isAccessible(string $featureKey, ?Authenticatable $user = null): bool
    {
        if (self::userIsSuperAdmin($user)) {
            return true;
        }

        return self::isEnabled($featureKey);
    }

    /**
     * Set a feature toggle state.
     */
    public static function set(string $featureKey, bool $state): void
    {
        Setting::updateOrCreate(
            ['key' => 'feature_' . $featureKey],
            ['value' => $state ? 'true' : 'false']
        );

        Cache::forget("feature_toggle_{$featureKey}");
    }

    private static function userIsSuperAdmin(?Authenticatable $user): bool
    {
        if (!$user) {
            return false;
        }

        $superAdminRole = Utils::getSuperAdminName();

        if (method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
            return true;
        }

        return strtolower((string) data_get($user, 'role')) === strtolower($superAdminRole);
    }
}
