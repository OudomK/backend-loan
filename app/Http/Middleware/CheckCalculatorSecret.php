<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCalculatorSecret
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->query('key') !== env('CALCULATOR_SECRET_KEY', 'qf-secret-2026')) {
            abort(404);
        }

        return $next($request);
    }
}
