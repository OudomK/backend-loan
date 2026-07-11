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
        if (app()->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        \App\Models\Loan::observe(\App\Observers\LoanObserver::class);

        $dashboardModels = [
            \App\Models\Loan::class,
            \App\Models\Borrower::class,
            \App\Models\Investor::class,
            \App\Models\RepaymentTransaction::class,
            \App\Models\Payment::class,
            \App\Models\Borrowing::class,
            \App\Models\CapitalShare::class,
            \App\Models\Setting::class,
        ];

        foreach ($dashboardModels as $model) {
            $model::observe(\App\Observers\DashboardStatsObserver::class);
        }

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
