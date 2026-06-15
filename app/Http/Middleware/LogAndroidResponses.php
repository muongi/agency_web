<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogAndroidResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Log exceptions that prevent a normal JSON response from being built.
            Log::error('ANDROID_API_EXCEPTION', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'uri' => $request->path(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }

        // Your API routes are under /api/v1, but Request::path() is usually just `v1/...`.
        if (! $request->is('v1/*') && ! $request->is('api/v1/*')) {
            return $response;
        }

        if ($response instanceof JsonResponse) {
            $payload = $response->getData(true);

            Log::info('ANDROID_API_RESPONSE', [
                'status' => $response->getStatusCode(),
                'ip' => $request->ip(),
                'method' => $request->method(),
                'uri' => $request->path(),
                'data' => $this->sanitizeForLog($payload),
            ]);
        }

        return $response;
    }

    /**
     * Remove/redact sensitive fields before writing to logs.
     *
     * @param  mixed  $value
     * @return mixed
     */
    private function sanitizeForLog($value)
    {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $sanitized[$key] = $this->sanitizeForLogItem((string) $key, $item);
            }

            return $sanitized;
        }

        return $value;
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    private function sanitizeForLogItem(string $key, $value)
    {
        $sensitiveKeys = [
            'pin',
            'otp',
            'otp_code',
            'otpCode',
            'password',
            'pin_code',
        ];

        if (in_array($key, $sensitiveKeys, true)) {
            return '[REDACTED]';
        }

        if ($key === 'card_number' || $key === 'account_number') {
            return $this->maskLast4($value);
        }

        // Also sanitize nested arrays/objects.
        if (is_array($value)) {
            return $this->sanitizeForLog($value);
        }

        return $value;
    }

    /**
     * Mask card/account values to last 4 digits.
     *
     * @param  mixed  $value
     */
    private function maskLast4($value): string
    {
        $str = (string) $value;
        $str = trim($str);

        if ($str === '') {
            return '';
        }

        if (mb_strlen($str) <= 4) {
            return $str;
        }

        return mb_substr($str, -4);
    }
}
