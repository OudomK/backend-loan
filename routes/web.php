<?php

use Illuminate\Support\Facades\Route;

Route::post('/admin/logout', \App\Http\Controllers\Auth\LogoutController::class)->name('filament.admin.auth.logout');

Route::get('/', function () {
    return view('welcome');
});
