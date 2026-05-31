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
        \App\Models\Loan::observe(\App\Observers\LoanObserver::class);
        // Disable Vite preload tags to avoid browser warnings for deferred CSS usage in SPA navigation.
        Vite::usePreloadTagAttributes(false);

        // Explicitly register ActivityPolicy for Audit Logs
        Gate::policy(\Spatie\Activitylog\Models\Activity::class, \App\Policies\ActivityPolicy::class);

        Event::subscribe(AuthEventSubscriber::class);

        Gate::before(function (?object $user, string $ability): ?bool {
            if ($user instanceof User) {
                if ($user->hasEffectivePermission($ability)) {
                    return true;
                }
            }

            return null;
        });
    }
}
