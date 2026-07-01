<?php

namespace App\Filament\Concerns;

use App\Services\FeatureToggle;
use Filament\Facades\Filament;

/**
 * Trait to check feature toggle before allowing resource access.
 *
 * Usage: Add `use ChecksFeatureToggle;` and set `protected static ?string $featureToggleKey = 'your_key';`
 */
trait ChecksFeatureToggle
{
    public static function shouldRegisterNavigation(): bool
    {
        if (static::getFeatureToggleKey() && !FeatureToggle::isAccessible(static::getFeatureToggleKey(), Filament::auth()->user())) {
            return false;
        }

        return parent::shouldRegisterNavigation();
    }

    public static function canAccess(): bool
    {
        if (static::getFeatureToggleKey() && !FeatureToggle::isAccessible(static::getFeatureToggleKey(), Filament::auth()->user())) {
            return false;
        }

        return parent::canAccess();
    }

    protected static function getFeatureToggleKey(): ?string
    {
        return static::$featureToggleKey ?? null;
    }
}
