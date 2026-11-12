<?php

namespace App\Http\Middleware;

use App\Support\SunuBusiness;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * SUNU-only mode: a super admin always works inside SUNU (no separate super-admin
 * area). Also repairs sessions left by an earlier "Switch to Superadmin".
 */
class EnsureMainBusinessUnit
{
    public function handle(Request $request, Closure $next)
    {
        SunuBusiness::forget(); // one lookup per request (long-running workers / tests)
        $user = Auth::user();
        if ($user && $user->isSuperAdmin() && SunuBusiness::locked()) {
            $main = SunuBusiness::main();
            if ((int) $user->current_business_unit_id !== (int) $main->id) {
                $user->forceFill(['current_business_unit_id' => $main->id])->save();
            }
        }

        return $next($request);
    }
}
