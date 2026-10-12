<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogTransactionApiRequest
{
    /**
     * @var list<string>
     */
    protected array $sensitiveKeys = [
        'password',
        'pin',
        'pin_hash',
        'otp',
        'biometric_template',
        'biometricTemplate',
        'fingerprint1',
        'fingerprint2',
        'fingerprint3',
        'fingerprint4',
    ];

    /**
     * Log incoming and outgoing transaction API payloads (cash deposit, withdrawal).
     */
    public function handle(Request $request, Closure $next)
    {
        $incomingPayload = $this->buildIncomingPayload($request);
        $sanitizedPayload = $this->sanitizeForLog($incomingPayload);

        Log::info('Transaction API incoming payload', [
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'full_url' => $request->fullUrl(),
            'payload' => $sanitizedPayload,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $this->safeHeaders($request),
        ]);

        $response = $next($request);

        $this->logResponsePayload($request, $response);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildIncomingPayload(Request $request): array
    {
        $routeParams = $request->route()?->parameters() ?? [];
        $query = $request->query();
        $body = $request->all();

        $payload = array_merge($routeParams, $query, $body);

        if ($payload === [] && $request->getContent() !== '') {
            $payload['raw_body'] = $request->getContent();
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sanitizeForLog(array $payload): array
    {
        $sanitized = [];
        foreach ($payload as $key => $value) {
            if ($key === 'fingerprints' && is_array($value)) {
                $sanitized[$key] = array_map(
                    fn ($item) => $item !== null && $item !== '' ? '[REDACTED]' : null,
                    $value
                );
                continue;
            }
            if (in_array($key, $this->sensitiveKeys, true)) {
                $sanitized[$key] = $value !== null && $value !== '' ? '[REDACTED]' : null;
                continue;
            }
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeForLog($value);
                continue;
            }
            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    protected function logResponsePayload(Request $request, mixed $response): void
    {
        if (! method_exists($response, 'getStatusCode')) {
            return;
        }

        $status = $response->getStatusCode();

        if ($response instanceof JsonResponse) {
            $decoded = json_decode($response->getContent(), true);
            Log::info('Transaction API response payload', [
                'endpoint' => $request->path(),
                'status' => $status,
                'payload' => json_last_error() === JSON_ERROR_NONE
                    ? $this->sanitizeForLog(is_array($decoded) ? $decoded : [])
                    : $response->getContent(),
            ]);

            return;
        }

        $contentType = $response->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($response->getContent(), true);
            Log::info('Transaction API response payload', [
                'endpoint' => $request->path(),
                'status' => $status,
                'payload' => json_last_error() === JSON_ERROR_NONE
                    ? $this->sanitizeForLog(is_array($decoded) ? $decoded : [])
                    : $response->getContent(),
            ]);

            return;
        }

        Log::info('Transaction API response payload', [
            'endpoint' => $request->path(),
            'status' => $status,
            'payload' => 'non_json',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function safeHeaders(Request $request): array
    {
        $authorization = $request->header('Authorization');
        $apiKey = $request->header('X-API-Key');

        return [
            'Accept' => $request->header('Accept'),
            'Content-Type' => $request->header('Content-Type'),
            'Authorization' => $authorization ? 'present' : 'missing',
            'X-API-Key' => $this->maskApiKey($apiKey),
        ];
    }

    protected function maskApiKey(?string $apiKey): string
    {
        if (! $apiKey || strlen($apiKey) < 4) {
            return $apiKey ? '***' : 'missing';
        }

        return '***'.substr($apiKey, -4);
    }
}
