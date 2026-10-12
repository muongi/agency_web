<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Account;
use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Services\KatoService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClientAccountController extends Controller
{
    /**
     * @var list<string>
     */
    private const FINGERPRINT_KEYS = ['fingerprint1', 'fingerprint2', 'fingerprint3', 'fingerprint4'];

    /**
     * @var list<string>
     */
    private const SENSITIVE_LOG_KEYS = ['password', 'pin', 'otp', 'otp_code', 'pin_code'];

    /**
     * Create a new client and open an account for them.
     *
     * Expected request format (multipart/form-data):
     * - business_unit_id: integer
     * - client[first_name], client[last_name], ... : client fields
     * - account[status], account[opening_date], ... : account fields
     * - photo: image file (jpeg/png/jpg/gif, max 2MB) – optionally client[photo]
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function openAccount(Request $request)
    {
        $sanitizedRequestData = $this->buildSanitizedRequestDataForLog($request);

        // Allow business_unit_id at root level or nested under client
        $businessUnitId = $request->input('business_unit_id')
            ?? $request->input('client.business_unit_id');

        // Validate business_unit_id
        $businessUnitValidator = Validator::make([
            'business_unit_id' => $businessUnitId,
        ], [
            'business_unit_id' => 'required|integer|exists:business_units,id',
        ]);

        if ($businessUnitValidator->fails()) {
            Log::warning('API Client Account Creation Failed - Business Unit Validation Failed', [
                'errors' => $businessUnitValidator->errors()->toArray(),
                'request_data' => $sanitizedRequestData,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $businessUnitValidator->errors(),
            ], 422);
        }

        // Verify business unit exists and is active
        $businessUnit = BusinessUnit::find($businessUnitId);
        if (!$businessUnit) {
            Log::error('API Client Account Creation Failed - Business Unit Not Found', [
                'business_unit_id' => $businessUnitId,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Business unit not found',
            ], 404);
        }

        $photoMetadata = $sanitizedRequestData['photo'] ?? null;

        Log::info('API Client Account Creation Request', [
            'business_unit_id' => $businessUnitId,
            'client_name' => $request->input('client.first_name') . ' ' . $request->input('client.last_name'),
            'client_email' => $request->input('client.email'),
            'client_phone' => $request->input('client.phone_number'),
            'has_photo' => is_array($photoMetadata),
            'photo_metadata' => $photoMetadata,
            'account_status' => $request->input('account.status'),
            'account_opening_date' => $request->input('account.opening_date'),
            'request_data' => $sanitizedRequestData,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $businessUnit->loadMissing('coreBankingSystem');
        $isKato = $businessUnit->isKatoEnabled();

        $clientRules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'home_address' => 'nullable|string',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widowed',
            'region' => 'nullable|string|max:255',
            'home_town' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'place_of_birth' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'nationality' => 'nullable|string|max:255',
            'salutation' => 'nullable|string|max:50',
            'next_of_kin_first_name' => 'nullable|string|max:255',
            'next_of_kin_middle_name' => 'nullable|string|max:255',
            'next_of_kin_last_name' => 'nullable|string|max:255',
            'next_of_kin_phone_number' => 'nullable|string|max:50',
            'next_of_kin_address' => 'nullable|string',
            'next_of_kin_gender' => 'nullable|in:Male,Female,Other',
            'fingerprint1' => 'nullable|string',
            'fingerprint2' => 'nullable|string',
            'fingerprint3' => 'nullable|string',
            'fingerprint4' => 'nullable|string',
            'biometrics_status' => 'nullable|boolean',
        ];

        if ($isKato) {
            $clientRules['gender'] = ['required', Rule::in(['Male', 'Female'])];
            $clientRules['id_passport'] = 'required|string|max:255';
            $clientRules['phone_number'] = 'required|string|max:50';
            $clientRules['year_of_birth'] = 'required|string|max:10';
        } else {
            $clientRules['id_passport'] = 'nullable|string|max:255';
            $clientRules['phone_number'] = 'nullable|string|max:50';
            $clientRules['gender'] = 'nullable|in:Male,Female,Other';
            $clientRules['year_of_birth'] = 'nullable|string|max:10';
        }

        $clientInput = $this->normalizeClientInput($request->input('client', []));
        $request->merge(['client' => $clientInput]);

        $clientValidator = Validator::make($clientInput, $clientRules);

        // Validate account data
        $accountValidator = Validator::make($request->input('account', []), [
            'account_number' => 'nullable|string|max:50',
            'balance' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'status' => 'required|in:active,inactive,suspended,closed',
            'opening_date' => 'required|date',
            'closing_date' => 'nullable|date|after:opening_date',
            'description' => 'nullable|string',
        ]);

        // Validate photo file separately (can be at root level or nested)
        $photoFile = null;
        if ($request->hasFile('photo')) {
            $photoFile = $request->file('photo');
        } elseif ($request->hasFile('client.photo')) {
            $photoFile = $request->file('client.photo');
        }

        // Validate photo if provided
        if ($photoFile) {
            $photoValidator = Validator::make(['photo' => $photoFile], [
                'photo' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($photoValidator->fails()) {
                $errors = [];
                if ($clientValidator->fails()) {
                    $errors['client'] = $clientValidator->errors()->toArray();
                }
                if ($accountValidator->fails()) {
                    $errors['account'] = $accountValidator->errors()->toArray();
                }
                $errors['photo'] = $photoValidator->errors()->toArray();

                Log::warning('API Client Account Creation Failed - Photo Validation Failed', [
                    'business_unit_id' => $businessUnitId,
                    'photo_errors' => $photoValidator->errors()->toArray(),
                    'request_data' => $sanitizedRequestData,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors,
                ], 422);
            }
        }

        // Check if client data is provided
        if (!$request->has('client')) {
            Log::warning('API Client Account Creation Failed - Client Data Missing', [
                'business_unit_id' => $businessUnitId,
                'request_data' => $sanitizedRequestData,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Client data is required',
                'errors' => ['client' => ['The client field is required.']],
            ], 422);
        }

        // Check if account data is provided
        if (!$request->has('account')) {
            Log::warning('API Client Account Creation Failed - Account Data Missing', [
                'business_unit_id' => $businessUnitId,
                'request_data' => $sanitizedRequestData,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Account data is required',
                'errors' => ['account' => ['The account field is required.']],
            ], 422);
        }

        // Merge validation errors
        $errors = [];
        if ($clientValidator->fails()) {
            $errors['client'] = $clientValidator->errors()->toArray();
        }
        if ($accountValidator->fails()) {
            $errors['account'] = $accountValidator->errors()->toArray();
        }

        $agent = null;
        if ($isKato) {
            $agentValidator = Validator::make($request->all(), [
                'agent_id' => 'required|integer|exists:agents,id',
            ]);

            if ($agentValidator->fails()) {
                $errors['agent_id'] = $agentValidator->errors()->toArray();
            } else {
                $agentId = (int) $request->input('agent_id');
                $agent = Agent::where('business_unit_id', $businessUnitId)->find($agentId);

                if (! $agent) {
                    $errors['agent_id'] = ["Agent with id '{$agentId}' not found for business unit {$businessUnitId}"];
                } elseif (! $agent->hasKatoCbsAccounts()) {
                    $errors['agent_id'] = ['Agent KATO float account is not synced. Re-register the agent with KATO or run kato:backfill-agent-accounts.'];
                }
            }
        }

        if (!empty($errors)) {
            Log::warning('API Client Account Creation Validation Failed', [
                'business_unit_id' => $businessUnitId,
                'errors' => $errors,
                'request_data' => $sanitizedRequestData,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors,
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Prepare client data
            $clientData = $clientValidator->validated();
            $clientData['business_unit_id'] = $businessUnitId;

            // Handle photo upload
            if ($photoFile) {
                $photoName = time() . '_' . uniqid() . '.' . $photoFile->getClientOriginalExtension();
                $photoPath = $photoFile->storeAs('client-photos', $photoName, 'public');
                $clientData['photo'] = $photoPath;
            }

            // Handle fingerprints
            $clientInput = $request->input('client', []);
            if (isset($clientInput['fingerprint1'])) {
                $clientData['fingerprint1'] = $clientInput['fingerprint1'];
            }
            if (isset($clientInput['fingerprint2'])) {
                $clientData['fingerprint2'] = $clientInput['fingerprint2'];
            }
            if (isset($clientInput['fingerprint3'])) {
                $clientData['fingerprint3'] = $clientInput['fingerprint3'];
            }
            if (isset($clientInput['fingerprint4'])) {
                $clientData['fingerprint4'] = $clientInput['fingerprint4'];
            }

            // Set biometrics_status: true if any fingerprint is provided, or use provided value
            if (isset($clientInput['biometrics_status'])) {
                $clientData['biometrics_status'] = filter_var($clientInput['biometrics_status'], FILTER_VALIDATE_BOOLEAN);
            } else {
                // Auto-set to true if any fingerprint is provided
                $hasFingerprint = !empty($clientInput['fingerprint1']) 
                    || !empty($clientInput['fingerprint2']) 
                    || !empty($clientInput['fingerprint3']) 
                    || !empty($clientInput['fingerprint4']);
                $clientData['biometrics_status'] = $hasFingerprint;
            }

            // Create client
            $client = Client::create($clientData);

            $account = null;
            $katoAccountsForResponse = collect();

            if ($businessUnit->isKatoEnabled()) {
                $katoService = new KatoService();
                $katoResult = $katoService->registerMember($client, $businessUnit, $agent);

                if (! $katoResult['success']) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => $katoResult['error'] ?? 'KATO member registration failed',
                    ], 422);
                }

                $accountSyncSummary = $katoService->syncMemberAccountsFromKato(
                    $client,
                    $businessUnit,
                    $katoResult['data']['accounts'] ?? []
                );

                $registrationError = null;
                if (! empty($accountSyncSummary['errors'])) {
                    $registrationError = implode('; ', $accountSyncSummary['errors']);
                }

                $client->update([
                    'kato_member_no' => $katoResult['data']['memberNo'] ?? null,
                    'kato_registration_error' => $registrationError,
                    'kato_api_response' => $katoResult['full_response'] ?? null,
                ]);

                $client->refresh();
                $katoAccountsForResponse = $client->accounts()
                    ->where('account_type', 'general')
                    ->orderBy('kato_account_code')
                    ->orderBy('id')
                    ->get();

                $requestedAccountNumber = $accountValidator->validated()['account_number'] ?? null;
                $account = $requestedAccountNumber
                    ? $katoAccountsForResponse->firstWhere('account_number', $requestedAccountNumber)
                    : $katoAccountsForResponse->first();

                if (! $account && $katoAccountsForResponse->isNotEmpty()) {
                    $account = $katoAccountsForResponse->first();
                }

                if (! $account) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'KATO member registered but no accounts were returned',
                    ], 422);
                }
            } else {
                // Prepare account data
                $accountData = $accountValidator->validated();

                // Generate account number if not provided
                if (empty($accountData['account_number'])) {
                    $accountData['account_number'] = Account::generateAccountNumber(
                        $businessUnitId,
                        'general',
                        $client->id
                    );
                } else {
                    // Check if account number is unique
                    $exists = Account::where('business_unit_id', $businessUnitId)
                        ->where('account_number', $accountData['account_number'])
                        ->exists();

                    if ($exists) {
                        DB::rollBack();

                        Log::warning('API Client Account Creation Failed - Account Number Exists', [
                            'business_unit_id' => $businessUnitId,
                            'account_number' => $accountData['account_number'],
                            'ip' => $request->ip(),
                        ]);

                        return response()->json([
                            'success' => false,
                            'message' => 'Account number already exists',
                            'errors' => ['account.account_number' => ['The account number has already been taken.']],
                        ], 422);
                    }
                }

                // Set account defaults
                $accountData['business_unit_id'] = $businessUnitId;
                $accountData['accountable_type'] = Client::class;
                $accountData['accountable_id'] = $client->id;
                $accountData['account_type'] = 'general';
                $accountData['balance'] = $accountData['balance'] ?? 0.00;
                $accountData['currency'] = $accountData['currency'] ?? config('currency.default', 'CFA');

                $account = Account::create($accountData);
                $katoAccountsForResponse = collect([$account]);
            }

            DB::commit();

            // Log successful creation
            Log::info('API Client Account Creation Success', [
                'client_id' => $client->id,
                'client_name' => $client->full_name,
                'client_email' => $client->email,
                'account_id' => $account->id,
                'account_number' => $account->account_number,
                'account_type' => $account->account_type,
                'account_balance' => $account->balance,
                'business_unit_id' => $businessUnitId,
                'has_photo' => $client->photo !== null,
                'ip' => $request->ip(),
            ]);

            // Log response
            Log::info('API Client Account Creation Response', [
                'client_id' => $client->id,
                'account_id' => $account->id,
                'account_number' => $account->account_number,
                'response_status' => 201,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Client and account created successfully',
                'data' => [
                    'client' => [
                        'id' => $client->id,
                        'first_name' => $client->first_name,
                        'middle_name' => $client->middle_name,
                        'last_name' => $client->last_name,
                        'full_name' => $client->full_name,
                        'email' => $client->email,
                        'phone_number' => $client->phone_number,
                        'id_passport' => $client->id_passport,
                        'photo' => $client->photo,
                        'photo_path' => $client->photo,
                        'photo_url' => $client->photo ? Storage::url($client->photo) : null,
                        'biometrics_status' => $client->biometrics_status,
                        'business_unit_id' => $client->business_unit_id,
                    ],
                    'account' => [
                        'id' => $account->id,
                        'account_number' => $account->account_number,
                        'kato_account_code' => $account->kato_account_code,
                        'account_type' => $account->account_type,
                        'balance' => number_format($account->balance, 2, '.', ''),
                        'currency' => $account->currency,
                        'status' => $account->status,
                        'opening_date' => $account->opening_date->format('Y-m-d'),
                        'description' => $account->description,
                    ],
                    'accounts' => $katoAccountsForResponse->map(fn ($acc) => [
                        'id' => $acc->id,
                        'account_number' => $acc->account_number,
                        'kato_account_code' => $acc->kato_account_code,
                        'account_type' => $acc->account_type,
                        'balance' => number_format($acc->balance, 2, '.', ''),
                        'currency' => $acc->currency,
                        'status' => $acc->status,
                        'opening_date' => $acc->opening_date->format('Y-m-d'),
                        'description' => $acc->description,
                    ])->values()->all(),
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('API Client Account Creation Failed: ' . $e->getMessage(), [
                'request_data' => $this->buildSanitizedRequestDataForLog($request),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create client and account',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSanitizedRequestDataForLog(Request $request): array
    {
        $requestData = $request->all();

        if (isset($requestData['photo'])) {
            unset($requestData['photo']);
        }
        if (isset($requestData['client']['photo'])) {
            unset($requestData['client']['photo']);
        }

        if ($request->hasFile('photo')) {
            $photoFile = $request->file('photo');
            $requestData['photo'] = [
                'filename' => $photoFile->getClientOriginalName(),
                'size' => $photoFile->getSize(),
                'mime_type' => $photoFile->getMimeType(),
            ];
        } elseif ($request->hasFile('client.photo')) {
            $photoFile = $request->file('client.photo');
            $requestData['photo'] = [
                'filename' => $photoFile->getClientOriginalName(),
                'size' => $photoFile->getSize(),
                'mime_type' => $photoFile->getMimeType(),
            ];
        }

        return $this->sanitizeRequestDataForLog($requestData);
    }

    /**
     * @param  array<string, mixed>  $requestData
     * @return array<string, mixed>
     */
    private function sanitizeRequestDataForLog(array $requestData): array
    {
        $sanitized = [];

        foreach ($requestData as $key => $value) {
            $sanitized[$key] = $this->sanitizeRequestDataValueForLog((string) $key, $value);
        }

        return $sanitized;
    }

    /**
     * @return mixed
     */
    private function sanitizeRequestDataValueForLog(string $key, mixed $value)
    {
        if (in_array($key, self::SENSITIVE_LOG_KEYS, true)) {
            return '[REDACTED]';
        }

        if (in_array($key, self::FINGERPRINT_KEYS, true)) {
            return $this->fingerprintPresenceForLog($value);
        }

        if (is_array($value)) {
            $nested = [];
            foreach ($value as $nestedKey => $nestedValue) {
                $nested[$nestedKey] = $this->sanitizeRequestDataValueForLog((string) $nestedKey, $nestedValue);
            }

            return $nested;
        }

        return $value;
    }

    /**
     * @return array{present: bool, length?: int}
     */
    private function fingerprintPresenceForLog(mixed $value): array
    {
        if ($value === null || $value === '') {
            return ['present' => false];
        }

        return [
            'present' => true,
            'length' => strlen((string) $value),
        ];
    }

    /**
     * @param  array<string, mixed>  $clientInput
     * @return array<string, mixed>
     */
    private function normalizeClientInput(array $clientInput): array
    {
        if (array_key_exists('biometrics_status', $clientInput)) {
            $value = $clientInput['biometrics_status'];

            if ($value === null || (is_string($value) && trim($value) === '')) {
                unset($clientInput['biometrics_status']);
            } elseif (! in_array($value, [true, false, 0, 1, '0', '1'], true)) {
                $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                if ($parsed !== null) {
                    $clientInput['biometrics_status'] = $parsed ? '1' : '0';
                }
            }
        }

        if (empty($clientInput['home_town'])) {
            if (! empty($clientInput['location'])) {
                $clientInput['home_town'] = $clientInput['location'];
            } elseif (! empty($clientInput['place_of_birth'])) {
                $clientInput['home_town'] = $clientInput['place_of_birth'];
            }
        }

        return $clientInput;
    }
}
