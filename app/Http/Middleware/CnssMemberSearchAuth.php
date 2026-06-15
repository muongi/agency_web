<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CnssMemberSearchAuth
{
    /**
     * CNSS member search without business_unit_id requires Sanctum authentication
     * so results are scoped to permitted business units in the controller.
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->filled('business_unit_id')) {
            return $next($request);
        }

        return app(Authenticate::class)->handle($request, $next, 'sanctum');
    }
}
