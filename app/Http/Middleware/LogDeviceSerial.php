<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Logs any device/terminal serial present on API requests so unknown
 * ANDROID_IDs from TPE devices can be copied into terminals.serial_number.
 */
class LogDeviceSerial
{
    public function handle(Request $request, Closure $next)
    {
        $serial = $this->extractSerial($request);
        $path = $request->path();
        $shouldLog = ($serial !== null && $serial !== '')
            || ($request->isMethod('post') && str_starts_with($path, 'api/v1/sunu/'));

        if ($shouldLog) {
            Log::info('API_DEVICE_SERIAL', [
                'device_serial' => $serial,
                'path' => $path,
                'method' => $request->method(),
                'business_unit_id' => $request->input('business_unit_id'),
                'agent_id' => $request->input('agent_id'),
                'username' => $request->input('username'),
                'payload_keys' => array_keys($request->all()),
                'files' => array_keys($request->allFiles()),
                'content_length' => $request->header('Content-Length'),
                'transfer_encoding' => $request->header('Transfer-Encoding'),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return $next($request);
    }

    protected function extractSerial(Request $request): ?string
    {
        if ($request->filled('device_serial')) {
            return trim((string) $request->input('device_serial'));
        }

        // POS enrollment sometimes sends ANDROID_ID as terminal_id (string, not numeric PK).
        if ($request->filled('terminal_id') && ! is_numeric($request->input('terminal_id'))) {
            return trim((string) $request->input('terminal_id'));
        }

        return null;
    }
}
