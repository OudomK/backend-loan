<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
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

        /** @var User $user */
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

            /** @var User $user */
            $user = $event->user;

            // Only clear session binding if this is the active session logging out
            // (not when SingleSession middleware kicks out an old/stale session)
            if ($user->current_session_id === session()->getId()) {
                $user->forceFill(['current_session_id' => null])->saveQuietly();
            }

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

    public function subscribe(Dispatcher $events): void
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
