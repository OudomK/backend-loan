<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    abort(404);
});

Route::get('/login', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');

// Helper route to logout via GET when stuck in 403 page
Route::get('/logout', function () {
    /** @var \App\Models\User|null $user */
    $user = \Illuminate\Support\Facades\Auth::guard('web')->user();
    if ($user) {
        $user->forceFill(['current_session_id' => null])->saveQuietly();
    }
    \Illuminate\Support\Facades\Auth::guard('web')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/admin/login');
});

// SSO route from Flutter App
Route::get('/admin/sso/{token}', function ($token) {
    $userId = null;

    try {
        $paddedToken = str_pad(strtr($token, '-_', '+/'), strlen($token) % 4 === 0 ? strlen($token) : strlen($token) + 4 - strlen($token) % 4, '=', STR_PAD_RIGHT);
        $encrypted = base64_decode($paddedToken, true);
        if ($encrypted !== false) {
            $payload = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
            if (($payload['expires_at'] ?? 0) >= now()->timestamp) {
                $userId = $payload['user_id'] ?? null;
            }
        }
    } catch (\Throwable) {
        $userId = null;
    }

    $userId ??= \Illuminate\Support\Facades\Cache::pull('sso_token_' . $token);
    if (!$userId) {
        abort(401, 'Invalid or expired SSO token.');
    }
    
    $user = \App\Models\User::find($userId);
    if ($user) {
        // Replace any stale browser session before authenticating into Filament.
        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        \Illuminate\Support\Facades\Auth::guard('web')->login($user);
        request()->session()->regenerate();

        // Auto-unlock admin panel for SSO users
        session(['admin_unlocked' => true]);

        // Enforce single-session: bind new session to this user
        $user->forceFill(['current_session_id' => session()->getId()])->saveQuietly();
    }
    
    return redirect('/admin');
})->name('admin.sso');

Route::middleware([\App\Http\Middleware\CheckCalculatorSecret::class])->group(function () {
    // Public Schedule Calculator Component
    Route::get('/calculator', \App\Livewire\ScheduleCalculator::class)->name('calculator');

    // Print Schedule Route
    Route::get('/calculator/print', function () {
        $schedule = session('print_schedule', []);
        $customer_info = session('print_customer_info', null);
        return view('schedule-print', compact('schedule', 'customer_info'));
    })->name('calculator.print');
});
