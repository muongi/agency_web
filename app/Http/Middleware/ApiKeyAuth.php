<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ApiKey;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get API key from header
        $apiKey = $request->header('X-API-Key') 
            ?? $request->header('Authorization') 
            ?? $request->input('api_key');

        // Remove "Bearer " prefix if present
        if ($apiKey && str_starts_with($apiKey, 'Bearer ')) {
            $apiKey = substr($apiKey, 7);
        }

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key is required',
            ], 401);
        }

        // Find API key
        $key = ApiKey::findByKey($apiKey);

        if (!$key || !$key->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive API key',
            ], 401);
        }

        // Update last used timestamp
        $key->updateLastUsed();

        // Attach business unit to request for use in controller
        $request->merge(['business_unit_id' => $key->business_unit_id]);
        $request->setUserResolver(function () use ($key) {
            return $key;
        });

        return $next($request);
    }
}

