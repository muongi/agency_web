<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Core banking web pages are hidden for the SUNU deployment (config sunu.core_banking).
 */
class EnsureCoreBanking
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('sunu.core_banking')) {
            abort(404);
        }

        return $next($request);
    }
}
