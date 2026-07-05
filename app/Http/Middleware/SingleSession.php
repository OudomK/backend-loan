<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce single-session-per-user for the Admin Panel.
 *
 * When a user logs in from a new browser/device the session ID stored on their
 * record is updated.  Any *other* browser still carrying the old session will
 * be compared here and forcefully logged out.
 */
class SingleSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user) {
            return $next($request);
        }

        // Re-read from DB to get the latest current_session_id
        // (the cached Eloquent instance may be stale within a long-lived request)
        $freshSessionId = \App\Models\User::query()
            ->where('id', $user->getAuthIdentifier())
            ->value('current_session_id');

        if ($freshSessionId && $freshSessionId !== session()->getId()) {
            // This session is NOT the latest one → kick out
            // Use Auth::logout without firing event to avoid clearing
            // the NEW session's current_session_id
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Redirect to login with a message
            return redirect()->route('filament.admin.auth.login')
                ->with('notification', [
                    'title' => 'គណនីនេះបានចូលពី Device/Browser ផ្សេង',
                    'body' => 'Session របស់អ្នកត្រូវបានបិទ ដោយសារគណនីនេះបាន Login ពីកន្លែងផ្សេង។',
                    'status' => 'warning',
                ]);
        }

        return $next($request);
    }
}
