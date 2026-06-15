<?php

namespace App\Services\Cbs;

use App\Models\BusinessUnit;
use App\Models\BusinessUnitCbsCredential;
use App\Models\CoreBankingSystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CbsAuthResolver
{
    /**
     * @return array<string, string>
     */
    public function resolveHeaders(
        CoreBankingSystem $cbs,
        BusinessUnit $businessUnit,
        BusinessUnitCbsCredential $credentials
    ): array {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $authType = $cbs->auth_type ?? 'jwt_bearer';
        $authConfig = $cbs->auth_config ?? [];

        if ($authType === 'basic') {
            $username = $credentials->username;
            $password = $credentials->password;
            if (! $username || ! $password) {
                throw new RuntimeException('CBS basic auth credentials are missing.');
            }
            $headers['Authorization'] = 'Basic '.base64_encode($username.':'.$password);
        } elseif ($authType === 'oauth_password') {
            $token = $this->resolveOAuthToken($cbs, $businessUnit, $credentials, $authConfig);
            $headers['Authorization'] = 'Bearer '.$token;
        } else {
            $token = $credentials->bearer_token;
            if (! $token) {
                throw new RuntimeException('CBS bearer token is missing.');
            }
            if ($credentials->isTokenExpired()) {
                if (! empty($authConfig['token_endpoint'])) {
                    $token = $this->resolveOAuthToken($cbs, $businessUnit, $credentials, $authConfig);
                } else {
                    throw new RuntimeException('CBS bearer token has expired.');
                }
            }
            $headers['Authorization'] = 'Bearer '.$token;
        }

        $extraHeaders = $credentials->extra_headers ?? [];
        if (is_array($extraHeaders)) {
            foreach ($extraHeaders as $key => $value) {
                if ($key && $value !== null && $value !== '') {
                    $headers[(string) $key] = (string) $value;
                }
            }
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $authConfig
     */
    private function resolveOAuthToken(
        CoreBankingSystem $cbs,
        BusinessUnit $businessUnit,
        BusinessUnitCbsCredential $credentials,
        array $authConfig
    ): string {
        $endpoint = $authConfig['token_endpoint'] ?? null;
        if (! $endpoint) {
            throw new RuntimeException('CBS token endpoint is not configured.');
        }

        $username = $credentials->username;
        $password = $credentials->password;
        if (! $username || ! $password) {
            throw new RuntimeException('CBS login credentials are missing.');
        }

        $usernameField = $authConfig['username_field'] ?? 'username';
        $passwordField = $authConfig['password_field'] ?? 'password';
        $body = [
            $usernameField => $username,
            $passwordField => $password,
        ];

        $extraFields = $credentials->extra_fields ?? [];
        if (is_array($extraFields)) {
            $body = array_merge($body, $extraFields);
        }

        $url = $this->buildUrl($cbs->base_url, $endpoint);
        $timeout = $cbs->timeout_seconds ?? 30;

        Log::info('CBS OAuth token request', [
            'business_unit_id' => $businessUnit->id,
            'url' => $url,
        ]);

        $response = Http::timeout($timeout)->post($url, $body);
        if (! $response->successful()) {
            throw new RuntimeException('CBS token request failed: HTTP '.$response->status());
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('CBS token response is invalid.');
        }

        $tokenPath = $authConfig['token_path'] ?? 'token';
        $mapper = new JsonPayloadMapper();
        $token = $mapper->getNestedValue($data, $tokenPath);

        if (! is_string($token) || $token === '') {
            throw new RuntimeException("CBS token not found at path '{$tokenPath}'.");
        }

        $credentials->bearer_token = $token;

        $expiresPath = $authConfig['token_expires_path'] ?? null;
        if ($expiresPath) {
            $expires = $mapper->getNestedValue($data, $expiresPath);
            if ($expires) {
                $credentials->token_expires_at = is_numeric($expires)
                    ? now()->addSeconds((int) $expires)
                    : now()->parse((string) $expires);
            }
        }

        $credentials->save();

        return $token;
    }

    private function buildUrl(?string $baseUrl, string $path): string
    {
        $base = rtrim((string) $baseUrl, '/');

        return $base.'/'.ltrim($path, '/');
    }
}
