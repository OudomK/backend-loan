<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use App\Listeners\AuthEventSubscriber;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Disable Vite preload tags to avoid browser warnings for deferred CSS usage in SPA navigation.
        Vite::usePreloadTagAttributes(false);

        // Explicitly register ActivityPolicy for Audit Logs
        Gate::policy(\Spatie\Activitylog\Models\Activity::class, \App\Policies\ActivityPolicy::class);

        Event::subscribe(AuthEventSubscriber::class);

        // Let admin users from the legacy role column bypass Filament/Shield authorization.
        // Spatie roles still work normally for users created from the Role/User screens.
        Gate::before(function (?object $user, string $ability): ?bool {
            if ($user instanceof User) {
                // Get the super admin role name dynamically from Filament Shield config
                $superAdminRole = class_exists('\BezhanSalleh\FilamentShield\Support\Utils')
                    ? \BezhanSalleh\FilamentShield\Support\Utils::getSuperAdminName()
                    : 'super_admin';

                // Allow if user has the Spatie super admin role, OR if their legacy role column matches
                if ($user->hasRole($superAdminRole) || strtolower((string) ($user->role ?? '')) === strtolower($superAdminRole)) {
                    return true;
                }
            }
            return null;
        });
    }
}
