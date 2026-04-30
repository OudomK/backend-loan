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

        Event::subscribe(AuthEventSubscriber::class);

        // Let admin users from the legacy role column bypass Filament/Shield authorization.
        // Spatie roles still work normally for users created from the Role/User screens.
        Gate::before(function (?object $user, string $ability): ?bool {
            if ($user instanceof User && in_array(strtolower((string) ($user->role ?? '')), ['admin', 'super_admin'], true)) {
                return true;
            }
            return null;
        });
    }
}
