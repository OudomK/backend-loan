<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/login', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');

// Helper route to logout via GET when stuck in 403 page
Route::get('/logout', function () {
    \Illuminate\Support\Facades\Auth::guard('web')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/admin/login');
});

// SSO route from Flutter App
Route::get('/admin/sso/{token}', function ($token) {
    $userId = \Illuminate\Support\Facades\Cache::pull('sso_token_' . $token);
    if (!$userId) {
        abort(401, 'Invalid or expired SSO token.');
    }
    
    $user = \App\Models\User::find($userId);
    if ($user) {
        // Authenticate into the web session used by Filament
        \Illuminate\Support\Facades\Auth::guard('web')->login($user);
    }
    
    return redirect('/admin');
})->name('admin.sso');
