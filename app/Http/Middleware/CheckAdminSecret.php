<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminSecret
{
    /**
     * Protect the admin panel with a secret key in the URL.
     * Users must visit /admin?key=YOUR_SECRET to unlock the session.
     * Once unlocked, the session persists until it expires.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If the request contains the correct secret key, unlock the session
        if ($request->has('key') && $request->query('key') === env('ADMIN_SECRET_KEY', 'qf-admin-secret-2026')) {
            session(['admin_unlocked' => true]);
            \Illuminate\Support\Facades\Cookie::queue('admin_unlocked', true, 0);
        }

        // If the session is not unlocked, return 404 (hide the admin panel)
        if (!session('admin_unlocked') && !$request->cookie('admin_unlocked')) {
            abort(404);
        }

        // If cookie is present but session is missing (e.g., after logout), restore session
        if ($request->cookie('admin_unlocked') && !session('admin_unlocked')) {
            session(['admin_unlocked' => true]);
        }

        return $next($request);
    }
}
