<?php

namespace App\Services;

use App\Models\BusinessUnit;
use App\Models\CoreBankingSystem;
use App\Models\Agent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KatoService
{
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
            if (!$cbs->base_url) {
                return [
                    'success' => false,
                    'error' => 'Base URL not configured for this Core Banking System'
                ];
            }

            // Generate admin credentials
            $adminFullName = $businessUnit->name . ' Administrator';
            $adminUsername = $this->generateUsername($businessUnit->name);
            $adminPassword = Str::random(12);

            // Build API request payload
            $payload = [
                'saccoName' => $businessUnit->name,
                'saccoEmail' => $businessUnit->email,
                'saccoPhone' => $businessUnit->phone,
                'adminFullName' => $adminFullName,
                'adminUsername' => $adminUsername,
                'adminPassword' => $adminPassword,
                'saccoType' => 'Normal',
            ];

            // Make API request
            // base_url should be the full endpoint URL: https://api.livecodesolutions.co.ke/api/v2/company/register
            $url = rtrim($cbs->base_url, '/');
            $url = $url . '/company/register';
            \Log::info('KATO Company Registration URL', ['url' => $url]);
            \Log::info('KATO Company Registration Payload', ['payload' => $payload]);

            $response = Http::timeout(30)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                \Log::info('KATO Company Registration Response', ['registration payload' => $payload]);
                if (isset($data['success']) && $data['success'] === true) {
                    return [
                        'success' => true,
                        'data' => [
                            'companyId' => $data['companyId'] ?? null,
                            'adminUserId' => $data['adminUserId'] ?? null,
                            'companyName' => $data['companyName'] ?? null,
                            'saccoType' => $data['saccoType'] ?? null,
                            'adminUsername' => $data['adminUsername'] ?? $adminUsername,
                            'message' => $data['message'] ?? null,
                            'adminPassword' => $adminPassword, // Store generated password for reference
                        ],
                        'full_response' => $data, // Store full API response
                        'error' => null
                    ];
                } else {
                    $errorMessage = $data['message'] ?? 'Registration failed';
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
                $errorMessage = $response->json()['message'] ?? 'HTTP Error: ' . $response->status();
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

            // Get CBS to get base_url
            $cbs = $businessUnit->coreBankingSystem;
            if (!$cbs || !$cbs->base_url) {
                return [
                    'success' => false,
                    'error' => 'Base URL not configured for this Core Banking System'
                ];
            }

            // Build agent full name
            $directorName = trim($agent->first_name . ' ' . $agent->second_name);
            $businessName = $directorName . ' Business';

            // Build API request payload
            $payload = [
                'companyId' => $businessUnit->kato_company_id,
                'businessName' => $businessName,
                'directorName' => $directorName,
                'idNo' => $agent->id_passport,
                'phoneNumber' => $agent->phone_number,
                'location' => $agent->physical_address,
                'geoCoordinates' => null, // Can be added later if needed
            ];

            // Make API request
            // Derive agent endpoint from company registration endpoint
            // Replace /company/register with /agency/onboarding/register
            $baseUrl = rtrim($cbs->base_url, '/');
            $url = str_replace('/company/register', '/agency/onboarding/register', $baseUrl);
            $url = $url . '/agency/onboarding/register';

            \Log::info('KATO Agent Registration URL', ['url' => $url]);
            \Log::info('KATO Agent Registration Payload', ['payload' => $payload]);

            $response = Http::timeout(30)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['success']) && $data['success'] === true) {
                    return [
                        'success' => true,
                        'data' => [
                            'agentId' => $data['agentId'] ?? null,
                            'businessName' => $data['businessName'] ?? null,
                        ],
                        'error' => null
                    ];
                } else {
                    $errorMessage = $data['message'] ?? 'Registration failed';
                    Log::error('KATO Agent Registration Failed', [
                        'agent_id' => $agent->id,
                        'response' => $data
                    ]);
                    return [
                        'success' => false,
                        'error' => $errorMessage
                    ];
                }
            } else {
                $errorMessage = $response->json()['message'] ?? 'HTTP Error: ' . $response->status();
                Log::error('KATO Agent Registration HTTP Error', [
                    'agent_id' => $agent->id,
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
