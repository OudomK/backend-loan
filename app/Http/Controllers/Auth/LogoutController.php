<?php

namespace App\Http\Controllers\Auth;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

class LogoutController
{
    public function __invoke(): LogoutResponse
    {
        $user = Filament::auth()->user();

        // Log the logout activity BEFORE destroying the session
        if ($user) {
            /** @var Model $user */
            activity('auth')
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties([
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'guard' => 'web',
                ])
                ->log('User logged out');
        }

        Filament::auth()->logout();

        session()->invalidate();
        session()->regenerateToken();

        return app(LogoutResponse::class);
    }
}
