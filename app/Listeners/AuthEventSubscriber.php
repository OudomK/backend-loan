<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Log;

class AuthEventSubscriber
{
    protected static array $processedEvents = [];

    public function handleUserLogin(Login $event): void
    {
        $eventId = 'login_' . ($event->user->id ?? 'unknown') . '_' . ($event->guard ?? 'default');
        if (isset(static::$processedEvents[$eventId])) {
            return;
        }
        static::$processedEvents[$eventId] = true;

        /** @var \Illuminate\Database\Eloquent\Model $user */
        $user = $event->user;

        activity('auth')
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'guard' => $event->guard ?? null,
            ])
            ->log('User logged in');
    }

    public function handleUserLogout(Logout $event): void
    {
        if ($event->user) {
            $eventId = 'logout_' . ($event->user->id ?? 'unknown') . '_' . ($event->guard ?? 'default');
            if (isset(static::$processedEvents[$eventId])) {
                return;
            }
            static::$processedEvents[$eventId] = true;

            /** @var \Illuminate\Database\Eloquent\Model $user */
            $user = $event->user;

            activity('auth')
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties([
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'guard' => $event->guard ?? null,
                ])
                ->log('User logged out');
        }
    }

    public function subscribe($events): void
    {
        $events->listen(
            Login::class,
            [self::class, 'handleUserLogin']
        );

        $events->listen(
            Logout::class,
            [self::class, 'handleUserLogout']
        );
    }
}
