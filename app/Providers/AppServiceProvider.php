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

        // Let super_admin (users.role column) bypass all Filament/Shield authorization
        // so the Admin Panel shows all resources/pages instead of only 4.
        Gate::before(function (?object $user, string $ability): ?bool {
            if ($user instanceof User && strtolower((string) ($user->role ?? '')) === 'super_admin') {
                return true;
            }
            return null;
        });
    }
}
