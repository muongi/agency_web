<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogCardApiRequest
{
    /**
     * @var list<string>
     */
    protected array $sensitiveKeys = ['password', 'pin', 'pin_hash'];

    /**
     * Log incoming and outgoing card API payloads for Android debugging.
     */
    public function handle(Request $request, Closure $next)
    {
        $incomingPayload = $this->buildIncomingPayload($request);

        Log::info('Card API incoming payload', [
            'method' => $request->method(),
            'path' => $request->path(),
            'full_url' => $request->fullUrl(),
            'payload' => $incomingPayload,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $this->safeHeaders($request),
        ]);

        $response = $next($request);

        $this->logResponsePayload($response);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildIncomingPayload(Request $request): array
    {
        $routeParams = $request->route()?->parameters() ?? [];
        $query = $request->query();
        $body = $request->except($this->sensitiveKeys);

        $payload = array_merge($routeParams, $query, $body);

        if ($payload === [] && $request->getContent() !== '') {
            $payload['raw_body'] = $request->getContent();
        }

        return $payload;
    }

    protected function logResponsePayload(mixed $response): void
    {
        if (! method_exists($response, 'getStatusCode')) {
            return;
        }

        $status = $response->getStatusCode();

        if ($response instanceof JsonResponse) {
            $decoded = json_decode($response->getContent(), true);
            Log::info('Card API response payload', [
                'status' => $status,
                'payload' => json_last_error() === JSON_ERROR_NONE ? $decoded : $response->getContent(),
            ]);

            return;
        }

        $contentType = $response->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($response->getContent(), true);
            Log::info('Card API response payload', [
                'status' => $status,
                'payload' => json_last_error() === JSON_ERROR_NONE ? $decoded : $response->getContent(),
            ]);

            return;
        }

        Log::info('Card API response payload', [
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
