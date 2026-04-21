<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Fallback login route used by framework/auth exception redirects.
Route::get('/login', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');
