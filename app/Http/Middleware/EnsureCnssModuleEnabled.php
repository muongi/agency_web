<?php

namespace App\Http\Middleware;

use App\Models\BusinessUnit;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCnssModuleEnabled
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->filled('business_unit_id')) {
                return $this->ensureBusinessUnitAllowsCnss(
                    BusinessUnit::find($request->input('business_unit_id')),
                    $request,
                    $next
                );
            }

            return $next($request);
        }

        if ($user->isSuperAdmin() && ! $user->current_business_unit_id) {
            return $next($request);
        }

        if ($request->filled('business_unit_id')) {
            return $this->ensureBusinessUnitAllowsCnss(
                BusinessUnit::find($request->input('business_unit_id')),
                $request,
                $next
            );
        }

        if ($user->current_business_unit_id) {
            return $this->ensureBusinessUnitAllowsCnss(
                BusinessUnit::find($user->current_business_unit_id),
                $request,
                $next
            );
        }

        if ($this->userHasCnssEnabledAccess($user)) {
            return $next($request);
        }

        return $this->forbidden($request);
    }

    private function ensureBusinessUnitAllowsCnss(?BusinessUnit $businessUnit, Request $request, Closure $next): Response
    {
        if (! $businessUnit || ! $businessUnit->isCnssEnabled()) {
            return $this->forbidden($request);
        }

        return $next($request);
    }

    private function userHasCnssEnabledAccess(User $user): bool
    {
        foreach ($user->businessUnits()->wherePivot('is_active', true)->get() as $businessUnit) {
            if ($businessUnit->isCnssEnabled() && $user->hasPermission('view-cnss-members', $businessUnit->id)) {
                return true;
            }
        }

        return false;
    }

    private function forbidden(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => __('common.cnss_module_disabled'),
            ], 403);
        }

        abort(403, __('common.cnss_module_disabled'));
    }
}
