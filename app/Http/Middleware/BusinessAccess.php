<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BusinessAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        // Super admin can access any business
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Regular users must have access to the current business unit
        if (!$user->current_business_unit_id) {
            return redirect()->route('dashboard')->with('error', 'No business unit selected.');
        }

        // Verify user actually belongs to this business unit
        if (!$user->hasBusinessUnit($user->current_business_unit_id)) {
            // User doesn't belong to this business unit, set to first available
            $firstBusinessUnit = $user->businessUnits()->first();
            if ($firstBusinessUnit) {
                $user->update(['current_business_unit_id' => $firstBusinessUnit->id]);
            } else {
                Auth::logout();
                return redirect()->route('login')->with('error', 'You do not have access to any business unit.');
            }
        }

        return $next($request);
    }
}

