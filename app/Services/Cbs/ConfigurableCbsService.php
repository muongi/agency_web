<?php

namespace App\Services\Cbs;

use App\Models\BusinessUnit;
use App\Models\BusinessUnitCbsTransactionMapping;
use App\Models\TransactionType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class ConfigurableCbsService
{
    public function __construct(
        private CbsContextBuilder $contextBuilder,
        private JsonPayloadMapper $payloadMapper,
        private CbsAuthResolver $authResolver
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array{success: bool, data?: array<string, mixed>, error?: string, full_response?: mixed, http_status?: int}
     */
    public function execute(
        BusinessUnit $businessUnit,
        TransactionType $transactionType,
        BusinessUnitCbsTransactionMapping $mapping,
        array $context
    ): array {
        try {
            $businessUnit->loadMissing(['coreBankingSystem', 'cbsCredentials']);
            $cbs = $businessUnit->coreBankingSystem;

            if (! $cbs || ! $cbs->isConfigurableDriver()) {
                return [
                    'success' => false,
                    'error' => 'Business unit is not configured for configurable CBS.',
                ];
            }

            if (! $cbs->base_url) {
                return [
                    'success' => false,
                    'error' => 'CBS base URL is not configured.',
                ];
            }

            $credentials = $businessUnit->cbsCredentials;
            if (! $credentials) {
                return [
                    'success' => false,
                    'error' => 'CBS credentials are not configured for this business unit.',
                ];
            }

            $sourceData = $this->contextBuilder->build($businessUnit, $context);
            $payload = $this->payloadMapper->mapRequest($mapping->request_mappings ?? [], $sourceData);

            $headers = $this->authResolver->resolveHeaders($cbs, $businessUnit, $credentials);
            $url = rtrim($cbs->base_url, '/').'/'.ltrim($mapping->endpoint_path, '/');
            $method = strtolower($mapping->http_method ?: 'post');
            $timeout = $cbs->timeout_seconds ?? 30;

            Log::info('CBS API outgoing request', [
                'business_unit_id' => $businessUnit->id,
                'transaction_type_id' => $transactionType->id,
                'method' => strtoupper($method),
                'url' => $url,
                'payload' => $this->sanitizeLogPayload($payload),
            ]);

            $client = Http::timeout($timeout)->withHeaders($headers);
            $response = match ($method) {
                'get' => $client->get($url, $payload),
                'post' => $client->post($url, $payload),
                'put' => $client->put($url, $payload),
                'patch' => $client->patch($url, $payload),
                default => throw new InvalidArgumentException("Unsupported HTTP method '{$method}'."),
            };

            $responseJson = $response->json();
            $responseBody = is_array($responseJson) ? $responseJson : ['raw' => $response->body()];

            Log::info('CBS API response', [
                'business_unit_id' => $businessUnit->id,
                'transaction_type_id' => $transactionType->id,
                'status' => $response->status(),
                'successful' => $response->successful(),
                'payload' => $responseBody,
            ]);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error' => $this->extractErrorMessage($mapping, $responseBody, 'HTTP Error: '.$response->status()),
                    'full_response' => $responseBody,
                    'http_status' => $response->status(),
                ];
            }

            if (! $this->isBusinessSuccess($mapping, $responseBody)) {
                return [
                    'success' => false,
                    'error' => $this->extractErrorMessage($mapping, $responseBody, 'CBS transaction failed'),
                    'full_response' => $responseBody,
                    'http_status' => $response->status(),
                ];
            }

            $mappedData = $this->payloadMapper->mapResponse(
                $mapping->response_mappings ?? [],
                $responseBody
            );

            return [
                'success' => true,
                'data' => $mappedData,
                'full_response' => $responseBody,
                'error' => null,
            ];
        } catch (InvalidArgumentException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } catch (RuntimeException $e) {
            Log::error('CBS API runtime error', [
                'business_unit_id' => $businessUnit->id,
                'transaction_type_id' => $transactionType->id,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            Log::error('CBS API exception', [
                'business_unit_id' => $businessUnit->id,
                'transaction_type_id' => $transactionType->id,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'CBS request failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $responseBody
     */
    private function isBusinessSuccess(BusinessUnitCbsTransactionMapping $mapping, array $responseBody): bool
    {
        if (! $mapping->success_path) {
            return true;
        }

        $value = $this->payloadMapper->getNestedValue($responseBody, $mapping->success_path);
        $expected = $mapping->success_value;

        if ($expected === null || $expected === '') {
            return (bool) $value;
        }

        if (is_bool($value)) {
            return $value === filter_var($expected, FILTER_VALIDATE_BOOLEAN);
        }

        return (string) $value === (string) $expected;
    }

    /**
     * @param  array<string, mixed>  $responseBody
     */
    private function extractErrorMessage(
        BusinessUnitCbsTransactionMapping $mapping,
        array $responseBody,
        string $fallback
    ): string {
        if ($mapping->error_message_path) {
            $message = $this->payloadMapper->getNestedValue($responseBody, $mapping->error_message_path);
            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        $message = $responseBody['message'] ?? $responseBody['error'] ?? null;

        return is_string($message) && $message !== '' ? $message : $fallback;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeLogPayload(array $payload): array
    {
        $sanitized = [];
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeLogPayload($value);
                continue;
            }
            if (preg_match('/pin|password|token|secret/i', (string) $key)) {
                $sanitized[$key] = $value !== null && $value !== '' ? '[REDACTED]' : null;
                continue;
            }
            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}
