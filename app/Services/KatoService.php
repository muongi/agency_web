<?php

namespace App\Services;

use App\Exceptions\KatoTokenRefreshException;
use App\Models\Account;
use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\CoreBankingSystem;
use App\Models\Client;
use App\Support\KatoAgencyUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KatoService
{
    private ?string $lastTokenRefreshError = null;

    public function getLastTokenRefreshError(): ?string
    {
        return $this->lastTokenRefreshError;
    }
    /**
     * Build a KATO endpoint URL from the CBS "API root" base URL.
     *
     * Convention: base_url is the agency root only, e.g.
     * https://api.saccoworks.co.ke/api/v2/agency
     */
    private function endpoint(CoreBankingSystem $cbs, string $path): string
    {
        $base = KatoAgencyUrl::resolveAgencyBase($cbs->base_url ?: config('kato.default_base_url'));

        if (! $base) {
            throw new \RuntimeException('CBS base_url not configured');
        }

        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Build a human-readable error from a KATO API JSON body.
     */
    private function formatApiErrorMessage(?array $data, string $fallback): string
    {
        if (! is_array($data)) {
            return $fallback;
        }

        $message = $data['message'] ?? $data['title'] ?? $fallback;
        $errors = $data['errors'] ?? null;

        if (! is_array($errors) || count($errors) === 0) {
            return $message;
        }

        $parts = [];
        if (array_is_list($errors)) {
            foreach ($errors as $err) {
                $parts[] = is_string($err) ? $err : json_encode($err);
            }
        } else {
            foreach ($errors as $field => $msgs) {
                if (is_array($msgs)) {
                    $text = implode(', ', array_map(
                        fn ($m) => is_string($m) ? $m : json_encode($m),
                        $msgs
                    ));
                    $parts[] = $field.': '.$text;
                } else {
                    $parts[] = $field.': '.(is_string($msgs) ? $msgs : json_encode($msgs));
                }
            }
        }

        $detail = implode('; ', $parts);

        return $detail ? trim($message.': '.$detail) : $message;
    }

    /**
     * Validate that a client has the fields required for KATO member registration.
     */
    public function validateClientForKatoMember(Client $client): ?string
    {
        if (! in_array($client->gender, ['Male', 'Female'], true)) {
            return __('common.gender_required_for_kato');
        }

        if (empty($client->id_passport)) {
            return __('common.kato_member_id_required');
        }

        if (empty($client->phone_number)) {
            return __('common.kato_member_phone_required');
        }

        if (empty($client->year_of_birth)) {
            return __('common.kato_member_dob_required');
        }

        return null;
    }

    /**
     * Refresh KATO JWT when expired or expiry is unknown, before an API call.
     *
     * @return array{success: bool, error?: string|null, refreshed?: bool}
     */
    public function ensureFreshKatoToken(BusinessUnit $businessUnit): array
    {
        if (! $businessUnit->shouldRefreshKatoTokenBeforeRequest()) {
            return [
                'success' => true,
                'refreshed' => false,
                'error' => null,
            ];
        }

        $refresh = $this->refreshKatoToken($businessUnit);
        if ($refresh['success']) {
            Log::info('KATO token proactive refresh', [
                'business_unit_id' => $businessUnit->id,
            ]);

            return [
                'success' => true,
                'refreshed' => true,
                'error' => null,
            ];
        }

        $error = $refresh['error'] ?? 'KATO token refresh failed';
        $this->lastTokenRefreshError = $error;
        Log::warning('KATO token proactive refresh failed', [
            'business_unit_id' => $businessUnit->id,
            'error' => $error,
        ]);

        return [
            'success' => false,
            'refreshed' => false,
            'error' => $error,
        ];
    }

    /**
     * Perform an authenticated request to the KATO agency API.
     *
     * Auth requirements (from API spec):
     * - X-Company-ID: {companyGuid}
     * - Authorization: Bearer {jwt}
     */
    public function authenticatedRequest(BusinessUnit $businessUnit, string $method, string $path, array $body = [])
    {
        $this->lastTokenRefreshError = null;
        $businessUnit->loadMissing('coreBankingSystem');
        $cbs = $businessUnit->coreBankingSystem;
        if (! $cbs) {
            throw new \RuntimeException('Business unit core banking system not set.');
        }

        if (! $businessUnit->kato_company_id) {
            throw new \RuntimeException('KATO companyId is missing.');
        }

        $ensure = $this->ensureFreshKatoToken($businessUnit);
        if (! $ensure['success']) {
            throw new KatoTokenRefreshException($ensure['error'] ?? 'KATO token refresh failed');
        }

        if ($ensure['refreshed'] ?? false) {
            $businessUnit->refresh();
        }

        $token = $this->resolveKatoToken($businessUnit);

        $response = $this->sendAuthenticatedRequest($businessUnit, $cbs, $method, $path, $body, $token);

        if ($response->status() === 401) {
            $refresh = $this->refreshKatoToken($businessUnit);
            if ($refresh['success']) {
                $this->lastTokenRefreshError = null;
                Log::info('KATO token refresh after 401', [
                    'business_unit_id' => $businessUnit->id,
                    'path' => $path,
                ]);
                $businessUnit->refresh();
                $token = $this->resolveKatoToken($businessUnit);
                $response = $this->sendAuthenticatedRequest($businessUnit, $cbs, $method, $path, $body, $token);
            } else {
                $this->lastTokenRefreshError = $refresh['error'] ?? 'KATO token refresh failed';
                Log::warning('KATO token refresh after 401 failed', [
                    'business_unit_id' => $businessUnit->id,
                    'path' => $path,
                    'error' => $this->lastTokenRefreshError,
                ]);
            }
        }

        return $response;
    }

    /**
     * Refresh KATO JWT using stored company admin credentials.
     *
     * @return array{success: bool, error?: string|null, data?: array<string, mixed>}
     */
    public function refreshKatoToken(BusinessUnit $businessUnit): array
    {
        $businessUnit->loadMissing('coreBankingSystem');
        $cbs = $businessUnit->coreBankingSystem;

        if (! $cbs) {
            return [
                'success' => false,
                'error' => 'Business unit core banking system not set.',
            ];
        }

        if (! $businessUnit->kato_company_id) {
            return [
                'success' => false,
                'error' => 'Business unit is not registered with KATO',
            ];
        }

        $credentials = $this->resolveKatoLoginCredentials($businessUnit);
        if (! $credentials['username'] || ! $credentials['password']) {
            return [
                'success' => false,
                'error' => 'KATO admin credentials not configured. Set username and password in Business Unit settings.',
            ];
        }

        $loginResult = $this->companyLogin($businessUnit, $cbs, $credentials);
        if (! $loginResult['success']) {
            return [
                'success' => false,
                'error' => 'KATO token refresh failed: '.($loginResult['error'] ?? 'Company login failed'),
            ];
        }

        $token = $loginResult['data']['token'] ?? null;
        $businessUnit->update([
            'kato_token' => $token,
            'kato_token_expires_at' => ! empty($loginResult['data']['expiresIn'])
                ? now()->addSeconds((int) $loginResult['data']['expiresIn'])
                : BusinessUnit::defaultKatoTokenExpiresAt(),
            'kato_registration_error' => null,
        ]);

        Log::info('KATO token refreshed', [
            'business_unit_id' => $businessUnit->id,
            'authorization' => $token ? 'Bearer '.$this->maskToken($token) : null,
        ]);

        return [
            'success' => true,
            'data' => $loginResult['data'],
            'error' => null,
        ];
    }

    /**
     * @return array{username: ?string, password: ?string}
     */
    private function resolveKatoLoginCredentials(BusinessUnit $businessUnit): array
    {
        $username = $businessUnit->kato_admin_username ?: $businessUnit->getKatoRegisteredAdminUsername();

        return [
            'username' => $username ?: null,
            'password' => $businessUnit->kato_admin_password ?: null,
        ];
    }

    private function formatHttpAuthError(int $status, ?string $refreshError = null): string
    {
        if ($status === 401 && $refreshError) {
            return $refreshError;
        }

        return 'HTTP Error: '.$status;
    }

    /**
     * @return array{success: false, error: string, http_status: int}
     */
    private function tokenRefreshFailureResult(KatoTokenRefreshException $e, string $operation): array
    {
        Log::error('KATO '.$operation.' aborted: token refresh required', [
            'error' => $e->getMessage(),
        ]);

        return [
            'success' => false,
            'error' => $e->getMessage(),
            'http_status' => 401,
        ];
    }

    private function resolveKatoToken(BusinessUnit $businessUnit): string
    {
        $token = $businessUnit->kato_token ?? null;

        if (! $token && is_array($businessUnit->kato_api_response)) {
            $token = $businessUnit->kato_api_response['data']['token']
                ?? $businessUnit->kato_api_response['token']
                ?? null;
        }

        if (! $token) {
            Log::error('KATO API request aborted', [
                'business_unit_id' => $businessUnit->id,
                'reason' => 'bearer_token_missing',
            ]);
            throw new \RuntimeException('KATO bearer token is missing. Register company first.');
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function sendAuthenticatedRequest(
        BusinessUnit $businessUnit,
        CoreBankingSystem $cbs,
        string $method,
        string $path,
        array $body,
        string $token
    ) {
        $companyId = $businessUnit->kato_company_id;
        $url = $this->endpoint($cbs, $path);

        $headers = [
            'X-Company-ID' => $companyId,
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
        ];

        $logPayload = $this->sanitizeKatoLogPayload($body);

        Log::info('KATO API outgoing request', [
            'business_unit_id' => $businessUnit->id,
            'method' => strtoupper($method),
            'url' => $url,
            'path' => $path,
            'x_company_id' => $companyId,
            'authorization' => 'Bearer '.$this->maskToken($token),
            'payload' => $logPayload,
        ]);

        $client = Http::timeout(30)->withHeaders($headers);

        $method = strtolower($method);
        if ($method === 'get') {
            $response = $client->get($url, $body);
        } elseif ($method === 'post') {
            $response = $client->post($url, $body);
        } else {
            throw new \InvalidArgumentException("Unsupported HTTP method '{$method}'.");
        }

        $responseJson = $response->json();
        Log::info('KATO API response', [
            'business_unit_id' => $businessUnit->id,
            'method' => strtoupper($method),
            'url' => $url,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'payload' => $responseJson ?? $response->body(),
        ]);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function sanitizeKatoLogPayload(array $body): array
    {
        $sanitized = [];
        foreach ($body as $key => $value) {
            if (in_array($key, ['memberPin', 'adminPassword', 'password'], true)) {
                $sanitized[$key] = $value !== null && $value !== '' ? '[REDACTED]' : null;
                continue;
            }
            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function maskToken(string $token): string
    {
        if (strlen($token) < 8) {
            return '***';
        }

        return '***'.substr($token, -4);
    }

    /**
     * Register a business unit (company) with KATO API
     *
     * @param BusinessUnit $businessUnit
     * @param CoreBankingSystem $cbs
     * @return array
     */
    public function registerCompany(BusinessUnit $businessUnit, CoreBankingSystem $cbs): array
    {
        try {
            if (! $cbs->base_url && ! config('kato.default_base_url')) {
                return [
                    'success' => false,
                    'error' => 'Base URL not configured for this Core Banking System',
                ];
            }

            $adminFullName = $businessUnit->kato_admin_name
                ?: ($businessUnit->name.' Administrator');
            $adminUsername = $businessUnit->kato_admin_username
                ?: $this->generateUsername($businessUnit->name);
            $adminPassword = $businessUnit->kato_admin_password
                ?: Str::random(12);

            $town = $this->formatKatoLocationText(
                $businessUnit->address ?: $businessUnit->mailing_address
            ) ?: 'Nairobi';

            // Build API request payload
            $payload = [
                'companyName' => $businessUnit->name,
                'phoneNumber' => $this->formatKatoPhoneNumber($businessUnit->phone),
                'email' => $businessUnit->email,
                'town' => $town,
                'currencyCode' => config('kato.default_currency'),
                'currencySymbol' => config('kato.default_currency'),
                'countryCode' => config('kato.default_country_code'),
                'adminUsername' => $adminUsername,
                'adminPassword' => $adminPassword,
                'adminName' => $adminFullName,
            ];

            // Make API request
            $url = $this->endpoint($cbs, 'company/register');
            Log::info('KATO Company Registration URL', ['url' => $url]);
            $logPayload = $payload;
            $logPayload['adminPassword'] = '[redacted]';
            Log::info('KATO Company Registration Payload', ['payload' => $logPayload]);

            $response = Http::timeout(30)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('KATO Company Registration Response', [
                    'business_unit_id' => $businessUnit->id,
                    'status' => $response->status(),
                    'response' => $data,
                ]);
                if (isset($data['success']) && $data['success'] === true) {
                    $companyData = $data['data'] ?? $data;

                    return [
                        'success' => true,
                        'data' => [
                            'companyId' => $companyData['companyId'] ?? $data['companyId'] ?? null,
                            'adminUserId' => $companyData['adminUserId'] ?? $data['adminUserId'] ?? null,
                            'companyName' => $companyData['companyName'] ?? $data['companyName'] ?? null,
                            'saccoType' => $companyData['saccoType'] ?? $data['saccoType'] ?? null,
                            'adminUsername' => $companyData['adminUsername'] ?? $data['adminUsername'] ?? $adminUsername,
                            'token' => $companyData['token'] ?? $data['token'] ?? null,
                            'expiresIn' => $companyData['expiresIn'] ?? $data['expiresIn'] ?? null,
                            'message' => $data['message'] ?? null,
                            'adminPassword' => $adminPassword,
                            'adminName' => $companyData['adminName'] ?? $data['adminName'] ?? $adminFullName,
                        ],
                        'full_response' => $data,
                        'error' => null,
                    ];
                } else {
                    $errorMessage = $this->formatApiErrorMessage($data, 'Registration failed');
                    Log::error('KATO Company Registration Failed', [
                        'business_unit_id' => $businessUnit->id,
                        'response' => $data
                    ]);
                    return [
                        'success' => false,
                        'error' => $errorMessage
                    ];
                }
            } else {
                $json = $response->json();
                $errorMessage = $this->formatApiErrorMessage($json, 'HTTP Error: ' . $response->status());
                Log::error('KATO Company Registration HTTP Error', [
                    'business_unit_id' => $businessUnit->id,
                    'url' => $url,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return [
                    'success' => false,
                    'error' => $errorMessage
                ];
            }
        } catch (\Exception $e) {
            Log::error('KATO Company Registration Exception', [
                'business_unit_id' => $businessUnit->id,
                'url' => isset($url) ? $url : null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'error' => 'Registration failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Authenticate company admin and refresh JWT (POST /company/login).
     */
    /**
     * @param  array{username: ?string, password: ?string}|null  $credentials
     */
    public function companyLogin(BusinessUnit $businessUnit, CoreBankingSystem $cbs, ?array $credentials = null): array
    {
        try {
            if (! $businessUnit->kato_company_id) {
                return [
                    'success' => false,
                    'error' => 'Business unit is not registered with KATO',
                ];
            }

            $credentials ??= $this->resolveKatoLoginCredentials($businessUnit);

            if (! $credentials['username'] || ! $credentials['password']) {
                return [
                    'success' => false,
                    'error' => 'KATO admin credentials not configured. Set username and password in Business Unit settings.',
                ];
            }

            $payload = [
                'companyId' => $businessUnit->kato_company_id,
                'username' => $credentials['username'],
                'password' => $credentials['password'],
            ];

            $url = $this->endpoint($cbs, 'company/login');
            Log::info('KATO Company Login URL', ['url' => $url, 'company_id' => $businessUnit->kato_company_id]);

            $response = Http::timeout(30)->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['success']) && $data['success'] === true) {
                    $loginData = $data['data'] ?? [];

                    return [
                        'success' => true,
                        'data' => [
                            'token' => $loginData['token'] ?? null,
                            'expiresIn' => $loginData['expiresIn'] ?? null,
                        ],
                        'full_response' => $data,
                        'error' => null,
                    ];
                }

                return [
                    'success' => false,
                    'error' => $this->formatApiErrorMessage($data, 'Company login failed'),
                    'full_response' => $data,
                ];
            }

            $json = is_array($response->json()) ? $response->json() : null;

            return [
                'success' => false,
                'error' => $this->formatApiErrorMessage($json, 'HTTP Error: '.$response->status()),
                'full_response' => $json,
            ];
        } catch (\Exception $e) {
            Log::error('KATO Company Login Exception', [
                'business_unit_id' => $businessUnit->id,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Company login failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Register an agent with KATO API
     *
     * @param Agent $agent
     * @param BusinessUnit $businessUnit
     * @return array
     */
    public function registerAgent(Agent $agent, BusinessUnit $businessUnit): array
    {
        try {
            // Check if business unit is registered with KATO
            if (!$businessUnit->kato_company_id) {
                return [
                    'success' => false,
                    'error' => 'Business unit must be registered with KATO first'
                ];
            }

            $cbs = $businessUnit->coreBankingSystem;
            if (! $cbs || strtolower($cbs->code) !== 'kato') {
                return [
                    'success' => false,
                    'error' => 'Core Banking System is not KATO',
                ];
            }

            if (! $cbs->base_url && ! config('kato.default_base_url')) {
                return [
                    'success' => false,
                    'error' => 'Base URL not configured for this Core Banking System',
                ];
            }

            $directorName = trim($agent->first_name.' '.$agent->second_name);
            $businessName = $directorName.' Business';

            $payload = [
                'companyId' => $businessUnit->kato_company_id,
                'businessName' => $businessName,
                'directorName' => $directorName,
                'idNo' => $agent->id_passport,
                'phoneNumber' => $this->formatKatoPhoneNumber($agent->phone_number),
                'location' => $this->formatKatoLocationText($agent->physical_address) ?: 'Nairobi',
            ];

            // Make API request
            $url = $this->endpoint($cbs, 'onboarding/register');

            Log::info('KATO Agent Registration URL', ['url' => $url]);
            Log::info('KATO Agent Registration Payload', ['payload' => $payload]);

            $response = Http::timeout(30)
                ->post($url, $payload);

            $responseBody = $response->json();
            if (! is_array($responseBody)) {
                $responseBody = ['raw' => $response->body()];
            }

            Log::info('KATO Agent Registration Response', [
                'agent_id' => $agent->id,
                'status' => $response->status(),
                'response' => $responseBody,
            ]);

            if ($response->successful()) {
                $data = is_array($response->json()) ? $response->json() : [];

                if (isset($data['success']) && $data['success'] === true) {
                    $agentData = $data['data'] ?? [];

                    return [
                        'success' => true,
                        'data' => [
                            'agentId' => $agentData['agentId'] ?? null,
                            'businessName' => $agentData['businessName'] ?? null,
                            'accounts' => $agentData['accounts'] ?? [],
                        ],
                        'error' => null,
                    ];
                }

                $errorMessage = $this->formatApiErrorMessage($data, 'Registration failed');
                Log::error('KATO Agent Registration Failed', [
                    'agent_id' => $agent->id,
                    'response' => $data,
                ]);

                return [
                    'success' => false,
                    'error' => $errorMessage,
                ];
            }

            $errorMessage = $this->formatApiErrorMessage(
                is_array($response->json()) ? $response->json() : null,
                'HTTP Error: '.$response->status()
            );
            Log::error('KATO Agent Registration HTTP Error', [
                'agent_id' => $agent->id,
                'url' => $url,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
            ];
        } catch (\Exception $e) {
            Log::error('KATO Agent Registration Exception', [
                'agent_id' => $agent->id,
                'url' => isset($url) ? $url : null,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'error' => 'Registration failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Register a client/member with KATO agency API.
     *
     * API endpoint: POST /members/register
     */
    public function registerMember(Client $client, BusinessUnit $businessUnit, Agent $agent): array
    {
        try {
            $cbs = $businessUnit->coreBankingSystem;
            if (! $cbs || strtolower($cbs->code) !== 'kato') {
                return [
                    'success' => false,
                    'error' => 'Core Banking System is not KATO',
                ];
            }

            if (! $businessUnit->kato_company_id) {
                return [
                    'success' => false,
                    'error' => 'Business unit must be registered with KATO first',
                ];
            }

            if (! $agent->hasKatoCbsAccounts()) {
                return [
                    'success' => false,
                    'error' => 'Agent KATO float account is not synced. Re-register the agent with KATO or run kato:backfill-agent-accounts.',
                ];
            }

            $validationError = $this->validateClientForKatoMember($client);
            if ($validationError !== null) {
                return [
                    'success' => false,
                    'error' => $validationError,
                ];
            }

            $town = $this->resolveKatoMemberTown($client, $businessUnit);
            $dob = $this->resolveKatoMemberDob($client);

            $payload = array_filter([
                'agentAccountNumber' => (string) $agent->katoFloatAccountNumber(),
                'firstName' => $client->first_name,
                'lastName' => $client->last_name,
                'idNo' => $client->id_passport,
                'phoneNumber' => $this->formatKatoPhoneNumber($client->phone_number),
                'gender' => $client->gender,
                'dob' => $dob,
                'town' => $town,
            ], fn ($value) => $value !== null && $value !== '');

            $url = $this->endpoint($cbs, 'members/register');

            Log::info('KATO Member Registration URL', ['url' => $url]);
            Log::info('KATO Member Registration Payload', [
                'client_id' => $client->id,
                'payload' => $payload,
            ]);

            $response = $this->authenticatedRequest($businessUnit, 'post', 'members/register', $payload);

            $responseBody = $response->json();
            if (! is_array($responseBody)) {
                $responseBody = ['raw' => $response->body()];
            }

            Log::info('KATO Member Registration Response', [
                'client_id' => $client->id,
                'status' => $response->status(),
                'response' => $responseBody,
            ]);

            if ($response->successful()) {
                $data = is_array($response->json()) ? $response->json() : [];

                if (isset($data['success']) && $data['success'] === true) {
                    $memberData = $data['data'] ?? [];

                    return [
                        'success' => true,
                        'data' => [
                            'memberNo' => $memberData['memberNo'] ?? null,
                            'accounts' => $memberData['accounts'] ?? [],
                        ],
                        'full_response' => $data,
                        'error' => null,
                    ];
                }

                $errorMessage = $this->formatApiErrorMessage($data, 'Member registration failed');
                Log::error('KATO Member Registration Failed', [
                    'client_id' => $client->id,
                    'response' => $data,
                ]);

                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'full_response' => $data,
                ];
            }

            $json = is_array($response->json()) ? $response->json() : null;
            $errorMessage = $this->formatApiErrorMessage($json, 'HTTP Error: '.$response->status());
            Log::error('KATO Member Registration HTTP Error', [
                'client_id' => $client->id,
                'url' => $url,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'full_response' => $json,
            ];
        } catch (\Exception $e) {
            Log::error('KATO Member Registration Exception', [
                'client_id' => $client->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Registration failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update local agent float/commission accounts from KATO onboarding/register accounts[].
     *
     * @param  array<int, array<string, mixed>>  $katoAccounts
     * @return array{updated: int, errors: array<int, string>}
     */
    public function syncAgentAccountsFromKato(Agent $agent, BusinessUnit $businessUnit, array $katoAccounts): array
    {
        $updated = 0;
        $errors = [];

        $typeByCode = [
            'FLOAT' => 'float',
            'COMM' => 'commission',
        ];

        foreach ($katoAccounts as $katoAccount) {
            if (! is_array($katoAccount)) {
                continue;
            }

            $accountNumber = $katoAccount['accountNumber'] ?? null;
            $code = isset($katoAccount['code']) ? strtoupper((string) $katoAccount['code']) : null;

            if (! $accountNumber || ! $code || ! isset($typeByCode[$code])) {
                continue;
            }

            $accountType = $typeByCode[$code];
            $description = $katoAccount['description'] ?? ('KATO '.ucfirst($accountType).' Account');
            $balance = $katoAccount['balance'] ?? 0;

            $conflict = Account::where('business_unit_id', $businessUnit->id)
                ->where('account_number', $accountNumber)
                ->where(function ($query) use ($agent) {
                    $query->where('accountable_type', '!=', Agent::class)
                        ->orWhere('accountable_id', '!=', $agent->id);
                })
                ->exists();

            if ($conflict) {
                $errors[] = "Account number {$accountNumber} already belongs to another agent or client";
                continue;
            }

            $existing = $agent->accounts()->where('account_type', $accountType)->first();

            if (! $existing) {
                Account::create([
                    'business_unit_id' => $businessUnit->id,
                    'accountable_type' => Agent::class,
                    'accountable_id' => $agent->id,
                    'account_type' => $accountType,
                    'account_number' => $accountNumber,
                    'balance' => $balance,
                    'description' => $description,
                    'currency' => config('currency.default', 'CFA'),
                    'status' => 'active',
                    'opening_date' => now()->toDateString(),
                ]);
                $updated++;

                continue;
            }

            $existing->update([
                'account_number' => $accountNumber,
                'balance' => $balance,
                'description' => $description,
            ]);
            $updated++;
        }

        return [
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Upsert local client accounts from KATO members/register accounts[] response.
     *
     * @param  array<int, array<string, mixed>>  $katoAccounts
     * @return array{created: int, updated: int, errors: array<int, string>}
     */
    public function syncMemberAccountsFromKato(Client $client, BusinessUnit $businessUnit, array $katoAccounts): array
    {
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($katoAccounts as $katoAccount) {
            if (! is_array($katoAccount)) {
                continue;
            }

            $accountNumber = $katoAccount['accountNumber'] ?? null;
            if (! $accountNumber) {
                continue;
            }

            $accountCode = isset($katoAccount['accountCode']) ? (string) $katoAccount['accountCode'] : null;
            $product = $katoAccount['product'] ?? 'Account';
            $balance = $katoAccount['balance'] ?? 0;
            $description = 'KATO '.$product.($accountCode ? ' ('.$accountCode.')' : '');

            $conflict = Account::where('business_unit_id', $businessUnit->id)
                ->where('account_number', $accountNumber)
                ->where(function ($query) use ($client) {
                    $query->where('accountable_type', '!=', Client::class)
                        ->orWhere('accountable_id', '!=', $client->id);
                })
                ->exists();

            if ($conflict) {
                $errors[] = "Account number {$accountNumber} already belongs to another client";
                continue;
            }

            $existingQuery = $client->accounts()->where('account_type', 'general');
            $existing = (clone $existingQuery)->where('account_number', $accountNumber)->first();

            if (! $existing && $accountCode) {
                $existing = (clone $existingQuery)->where('kato_account_code', $accountCode)->first();
            }

            $accountFields = [
                'account_number' => $accountNumber,
                'kato_account_code' => $accountCode,
                'balance' => $balance,
                'description' => $description,
            ];

            if ($existing) {
                $existing->update($accountFields);
                $updated++;
            } else {
                Account::create(array_merge($accountFields, [
                    'business_unit_id' => $businessUnit->id,
                    'accountable_type' => Client::class,
                    'accountable_id' => $client->id,
                    'account_type' => 'general',
                    'currency' => config('currency.default', 'CFA'),
                    'status' => 'active',
                    'opening_date' => now()->toDateString(),
                ]));
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Fetch agent float/commission from KATO GET /float and persist to local accounts.
     *
     * @return array{success: bool, data?: array<string, mixed>, error?: string|null, sync?: array{updated: int, errors: array<int, string>}, full_response?: mixed}
     */
    public function syncAgentFloatBalancesFromKato(Agent $agent, BusinessUnit $businessUnit): array
    {
        if (! $businessUnit->isKatoEnabled()) {
            return [
                'success' => false,
                'error' => 'Business unit is not configured for KATO',
            ];
        }

        $floatAccountNumber = $agent->katoFloatAccountNumber();
        if (! $floatAccountNumber && $agent->kato_agent_id) {
            $floatAccountNumber = $agent->kato_agent_id.'FLOAT';
        }

        if (! $floatAccountNumber) {
            return [
                'success' => false,
                'error' => 'Agent KATO float account is not synced. Re-register the agent with KATO or run kato:backfill-agent-accounts.',
            ];
        }

        $result = $this->getAgentFloat($businessUnit, $floatAccountNumber);

        if (! $result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Failed to fetch agent float from KATO',
                'full_response' => $result['full_response'] ?? null,
            ];
        }

        $floatData = $result['data'] ?? [];

        $accounts = [
            [
                'accountNumber' => $floatData['floatAccountNumber'] ?? $floatAccountNumber,
                'code' => 'FLOAT',
                'description' => 'Agent Float Account',
                'balance' => $floatData['floatAccountBalance'] ?? 0,
            ],
        ];

        if (! empty($floatData['commissionAccountNumber'])) {
            $accounts[] = [
                'accountNumber' => $floatData['commissionAccountNumber'],
                'code' => 'COMM',
                'description' => 'Agent Commission Account',
                'balance' => $floatData['commissionAccountBalance'] ?? 0,
            ];
        }

        $sync = $this->syncAgentAccountsFromKato($agent, $businessUnit, $accounts);

        return [
            'success' => true,
            'data' => $floatData,
            'sync' => $sync,
            'error' => ! empty($sync['errors']) ? implode('; ', $sync['errors']) : null,
            'full_response' => $result['full_response'] ?? null,
        ];
    }

    /**
     * GET /float?agentAccountNumber=...
     *
     * @return array{success: bool, data?: array<string, mixed>, error?: string|null, full_response?: mixed}
     */
    public function getAgentFloat(BusinessUnit $businessUnit, string $agentAccountNumber): array
    {
        try {
            $response = $this->authenticatedRequest(
                $businessUnit,
                'get',
                'float',
                ['agentAccountNumber' => $agentAccountNumber]
            );

            $data = is_array($response->json()) ? $response->json() : [];

            if ($response->successful() && isset($data['success']) && $data['success'] === true) {
                return [
                    'success' => true,
                    'data' => $data['data'] ?? [],
                    'full_response' => $data,
                    'error' => null,
                ];
            }

            return [
                'success' => false,
                'error' => $this->formatApiErrorMessage($data, 'Failed to fetch agent float'),
                'full_response' => $data,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch agent float: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Post a cash deposit to KATO: POST /transactions/deposit
     *
     * @param  array<string, mixed>  $payload
     */
    public function deposit(BusinessUnit $businessUnit, array $payload): array
    {
        try {
            $response = $this->authenticatedRequest($businessUnit, 'post', 'transactions/deposit', $payload);
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['success']) && $data['success'] === true) {
                    return [
                        'success' => true,
                        'data' => [
                            'transactionId' => $data['transactionId'] ?? null,
                        ],
                        'full_response' => $data,
                        'error' => null,
                    ];
                }

                return [
                    'success' => false,
                    'error' => $data['message'] ?? 'Deposit failed',
                    'full_response' => $data,
                ];
            }

            $json = $response->json();
            $errorMessage = $this->formatApiErrorMessage(
                $json,
                $this->formatHttpAuthError($response->status(), $this->getLastTokenRefreshError())
            );
            Log::error('KATO deposit HTTP error', [
                'business_unit_id' => $businessUnit->id,
                'status' => $response->status(),
                'request_payload' => $this->sanitizeKatoLogPayload($payload),
                'response' => $json ?? $response->body(),
                'error' => $errorMessage,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'full_response' => $json,
                'http_status' => $response->status(),
            ];
        } catch (KatoTokenRefreshException $e) {
            return $this->tokenRefreshFailureResult($e, 'deposit');
        } catch (\Exception $e) {
            Log::error('KATO deposit exception', [
                'business_unit_id' => $businessUnit->id,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Deposit failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Post a cash withdrawal to KATO: POST /transactions/withdraw
     *
     * @param  array<string, mixed>  $payload
     */
    public function withdraw(BusinessUnit $businessUnit, array $payload): array
    {
        try {
            $sanitizedPayload = $this->sanitizeKatoLogPayload($payload);

            Log::info('KATO withdraw calling', [
                'business_unit_id' => $businessUnit->id,
                'path' => 'transactions/withdraw',
                'request_payload' => $sanitizedPayload,
            ]);

            $response = $this->authenticatedRequest($businessUnit, 'post', 'transactions/withdraw', $payload);
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['success']) && $data['success'] === true) {
                    Log::info('KATO withdraw success', [
                        'business_unit_id' => $businessUnit->id,
                        'request_payload' => $sanitizedPayload,
                        'transaction_id' => $data['transactionId'] ?? null,
                        'response' => $data,
                    ]);

                    return [
                        'success' => true,
                        'data' => [
                            'transactionId' => $data['transactionId'] ?? null,
                        ],
                        'full_response' => $data,
                        'error' => null,
                    ];
                }

                Log::info('KATO withdraw business_error', [
                    'business_unit_id' => $businessUnit->id,
                    'request_payload' => $sanitizedPayload,
                    'response' => $data,
                ]);

                return [
                    'success' => false,
                    'error' => $data['message'] ?? 'Withdrawal failed',
                    'full_response' => $data,
                ];
            }

            $json = $response->json();
            $errorMessage = $this->formatApiErrorMessage(
                $json,
                $this->formatHttpAuthError($response->status(), $this->getLastTokenRefreshError())
            );
            Log::error('KATO withdraw HTTP error', [
                'business_unit_id' => $businessUnit->id,
                'status' => $response->status(),
                'request_payload' => $this->sanitizeKatoLogPayload($payload),
                'response' => $json ?? $response->body(),
                'error' => $errorMessage,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'full_response' => $json,
                'http_status' => $response->status(),
            ];
        } catch (KatoTokenRefreshException $e) {
            return $this->tokenRefreshFailureResult($e, 'withdraw');
        } catch (\Exception $e) {
            Log::error('KATO withdraw exception', [
                'business_unit_id' => $businessUnit->id,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Withdrawal failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * POST /transactions/balance-enquiry
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, data?: array<string, mixed>, error?: string|null, full_response?: mixed}
     */
    public function balanceEnquiry(BusinessUnit $businessUnit, array $payload): array
    {
        try {
            $response = $this->authenticatedRequest($businessUnit, 'post', 'transactions/balance-enquiry', $payload);
            $data = is_array($response->json()) ? $response->json() : [];

            if ($response->successful() && isset($data['success']) && $data['success'] === true) {
                return [
                    'success' => true,
                    'data' => $data['data'] ?? [],
                    'full_response' => $data,
                    'error' => null,
                ];
            }

            return [
                'success' => false,
                'error' => $this->formatApiErrorMessage(
                    $data,
                    $this->formatHttpAuthError($response->status(), $this->getLastTokenRefreshError())
                ),
                'full_response' => $data,
                'http_status' => $response->status(),
            ];
        } catch (KatoTokenRefreshException $e) {
            return $this->tokenRefreshFailureResult($e, 'balance-enquiry');
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Balance enquiry failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * POST /transactions/mini-statement
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, data?: array<string, mixed>, error?: string|null, full_response?: mixed}
     */
    public function miniStatement(BusinessUnit $businessUnit, array $payload, int $limit = 5): array
    {
        try {
            $path = 'transactions/mini-statement?limit='.max(1, min(50, $limit));
            $response = $this->authenticatedRequest($businessUnit, 'post', $path, $payload);
            $data = is_array($response->json()) ? $response->json() : [];

            if ($response->successful() && isset($data['success']) && $data['success'] === true) {
                return [
                    'success' => true,
                    'data' => $data['data'] ?? [],
                    'full_response' => $data,
                    'error' => null,
                ];
            }

            return [
                'success' => false,
                'error' => $this->formatApiErrorMessage(
                    $data,
                    $this->formatHttpAuthError($response->status(), $this->getLastTokenRefreshError())
                ),
                'full_response' => $data,
                'http_status' => $response->status(),
            ];
        } catch (KatoTokenRefreshException $e) {
            return $this->tokenRefreshFailureResult($e, 'mini-statement');
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Mini-statement failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * GET /members/{memberNo}/balances
     *
     * @return array{success: bool, data?: array<string, mixed>, error?: string|null, full_response?: mixed}
     */
    public function getMemberBalances(BusinessUnit $businessUnit, string $memberNo): array
    {
        try {
            $path = 'members/'.urlencode($memberNo).'/balances';
            $response = $this->authenticatedRequest($businessUnit, 'get', $path);

            $data = is_array($response->json()) ? $response->json() : [];

            if ($response->successful() && isset($data['success']) && $data['success'] === true) {
                $memberData = $data['data'] ?? [];

                return [
                    'success' => true,
                    'data' => [
                        'memberNo' => $memberData['memberNo'] ?? $memberNo,
                        'balances' => $memberData['balances'] ?? [],
                    ],
                    'full_response' => $data,
                    'error' => null,
                ];
            }

            return [
                'success' => false,
                'error' => $this->formatApiErrorMessage($data, 'Failed to fetch member balances'),
                'full_response' => $data,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch member balances: '.$e->getMessage(),
            ];
        }
    }

    /**
     * GET /members/{memberNo}/mini-statement
     *
     * @return array{success: bool, data?: array<string, mixed>, error?: string|null, full_response?: mixed}
     */
    public function getMemberMiniStatement(BusinessUnit $businessUnit, string $memberNo, int $limit = 5): array
    {
        try {
            $path = 'members/'.urlencode($memberNo).'/mini-statement';
            $response = $this->authenticatedRequest($businessUnit, 'get', $path, ['limit' => $limit]);

            $data = is_array($response->json()) ? $response->json() : [];

            if ($response->successful() && isset($data['success']) && $data['success'] === true) {
                $memberData = $data['data'] ?? [];

                return [
                    'success' => true,
                    'data' => [
                        'memberNo' => $memberData['memberNo'] ?? $memberNo,
                        'transactions' => $memberData['transactions'] ?? [],
                    ],
                    'full_response' => $data,
                    'error' => null,
                ];
            }

            return [
                'success' => false,
                'error' => $this->formatApiErrorMessage($data, 'Failed to fetch mini-statement'),
                'full_response' => $data,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch mini-statement: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Update local client account balances from KATO balances response.
     *
     * @param  array<int, array<string, mixed>>  $balances
     */
    public function syncBalancesToLocalAccounts(Client $client, array $balances): void
    {
        foreach ($balances as $balanceRow) {
            if (! is_array($balanceRow)) {
                continue;
            }

            $accountCode = isset($balanceRow['accountCode']) ? (string) $balanceRow['accountCode'] : null;
            if ($accountCode === null || $accountCode === '') {
                continue;
            }

            $account = $client->accounts()
                ->where('account_type', 'general')
                ->where('kato_account_code', $accountCode)
                ->first();

            if ($account && array_key_exists('balance', $balanceRow)) {
                $account->update(['balance' => $balanceRow['balance']]);
            }
        }
    }

    /**
     * Resolve town/location for KATO member registration (short CBS column).
     */
    private function resolveKatoMemberTown(Client $client, BusinessUnit $businessUnit): string
    {
        $source = $client->home_town
            ?: $client->location
            ?: $client->place_of_birth
            ?: $client->region
            ?: ($businessUnit->address ?: 'Nairobi');

        $maxLength = max(1, (int) config('kato.member_town_max_length', 50));

        return $this->formatKatoLocationText($source, $maxLength) ?: 'Nairobi';
    }

    /**
     * Resolve yyyy-MM-dd date of birth for KATO member registration.
     */
    private function resolveKatoMemberDob(Client $client): ?string
    {
        if ($client->date_of_birth) {
            $dob = $client->date_of_birth instanceof \DateTimeInterface
                ? $client->date_of_birth->format('Y-m-d')
                : (string) $client->date_of_birth;

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) === 1) {
                return $dob;
            }
        }

        if ($client->year_of_birth) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $client->year_of_birth) === 1) {
                return (string) $client->year_of_birth;
            }

            if (preg_match('/^\d{4}$/', (string) $client->year_of_birth) === 1) {
                return (string) $client->year_of_birth.'-01-01';
            }
        }

        return null;
    }

    /**
     * Normalize a phone number for KATO API payloads (strip whitespace and non-dial chars).
     */
    private function formatKatoPhoneNumber(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $trimmed = trim($phone);
        $hasPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D/', '', $trimmed) ?? '';

        if ($digits === '') {
            return null;
        }

        return ($hasPlus ? '+' : '').$digits;
    }

    /**
     * Normalize location/town text for KATO API payloads (single line, max length).
     */
    private function formatKatoLocationText(?string $text, int $maxLength = 100): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $normalized = preg_replace('/[\r\n\t]+/', ' ', $text);
        $normalized = preg_replace('/\s+/', ' ', $normalized ?? '');
        $normalized = trim($normalized);

        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) > $maxLength) {
            $normalized = mb_substr($normalized, 0, $maxLength);
        }

        return $normalized;
    }

    /**
     * Generate a username from business unit name
     *
     * @param string $name
     * @return string
     */
    private function generateUsername(string $name): string
    {
        $username = Str::slug($name, '');
        $username = strtolower($username);

        // Ensure minimum 4 characters
        if (strlen($username) < 4) {
            $username = $username . 'admin';
        }

        // Limit to reasonable length
        if (strlen($username) > 20) {
            $username = substr($username, 0, 20);
        }

        return $username;
    }
}
