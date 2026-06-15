<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCnssContributionRequest;
use App\Http\Requests\Api\StoreCnssEnrollmentRequest;
use App\Models\Account;
use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\CnssContribution;
use App\Models\CnssVoluntaryEnrollment;
use App\Models\Terminal;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\CnssIdentifierService;
use App\Services\TransactionCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CnssController extends Controller
{
    public function __construct(
        protected CnssIdentifierService $identifierService,
        protected TransactionCalculationService $calculationService
    ) {
    }

    public function storeEnrollment(StoreCnssEnrollmentRequest $request): JsonResponse
    {
        try {
            $businessUnitId = (int) $request->input('business_unit_id');
            $businessUnit = BusinessUnit::find($businessUnitId);
            if (! $businessUnit || ! $businessUnit->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business unit not found or inactive',
                ], 404);
            }

            $resolvedTerminalId = null;
            $resolvedAgentId = null;

            if ($request->filled('device_serial')) {
                $deviceSerial = (string) $request->input('device_serial');
                $resolved = $this->resolveEnrollmentTerminalFromSerial($businessUnitId, $deviceSerial, 'device_serial');
                if ($resolved instanceof JsonResponse) {
                    return $resolved;
                }
                $resolvedTerminalId = $resolved['terminal']->id;
                $resolvedAgentId = $resolved['agent']->id;
            } else {
                if ($request->filled('agent_id')) {
                    $agent = Agent::where('business_unit_id', $businessUnitId)->find($request->input('agent_id'));
                    if (! $agent) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Agent not found for this business unit',
                        ], 422);
                    }
                    $resolvedAgentId = $agent->id;
                }

                if ($request->filled('terminal_id')) {
                    $terminalSerial = (string) $request->input('terminal_id');
                    $resolved = $this->resolveEnrollmentTerminalFromSerial($businessUnitId, $terminalSerial, 'terminal_id');
                    if ($resolved instanceof JsonResponse) {
                        return $resolved;
                    }
                    $resolvedTerminalId = $resolved['terminal']->id;
                    $resolvedAgentId = $resolved['agent']->id;
                }
            }

            $status = (string) config('cnss.default_enrollment_status', CnssVoluntaryEnrollment::STATUS_APPROVED);
            if (! in_array($status, [
                CnssVoluntaryEnrollment::STATUS_PENDING,
                CnssVoluntaryEnrollment::STATUS_APPROVED,
                CnssVoluntaryEnrollment::STATUS_REJECTED,
            ], true)) {
                $status = CnssVoluntaryEnrollment::STATUS_APPROVED;
            }

            $today = now()->toDateString();

            $identification = $request->input('identification', []);
            $other = $request->input('other_information', []);
            $beneficiary = $request->input('beneficiary', []);

            $enrollment = DB::transaction(function () use (
                $request,
                $businessUnitId,
                $status,
                $today,
                $identification,
                $other,
                $beneficiary,
                $resolvedTerminalId,
                $resolvedAgentId
            ) {
                $numbers = $this->identifierService->allocateNumbers($businessUnitId);

                $homeAddress = isset($identification['address']) ? (string) $identification['address'] : null;

                $client = Client::create([
                    'business_unit_id' => $businessUnitId,
                    'first_name' => (string) ($identification['first_names'] ?? 'Unknown'),
                    'last_name' => (string) ($identification['last_name'] ?? 'Unknown'),
                    'phone_number' => isset($identification['phone']) ? (string) $identification['phone'] : null,
                    'email' => isset($identification['email']) ? (string) $identification['email'] : null,
                    'home_address' => $homeAddress,
                ]);

                $accountNumber = Account::generateAccountNumber($businessUnitId, 'general', $client->id);

                Account::create([
                    'business_unit_id' => $businessUnitId,
                    'accountable_type' => Client::class,
                    'accountable_id' => $client->id,
                    'account_type' => 'general',
                    'account_number' => $accountNumber,
                    'opening_date' => $today,
                    'status' => 'active',
                    'balance' => 0,
                    'currency' => config('currency.default', 'CFA'),
                ]);

                $otherArray = is_array($other) ? $other : [];
                // Client-supplied bank account (if any) is kept as declared_bank_account_number; bank_account_number becomes the generated ledger.
                $declaredBank = isset($otherArray['bank_account_number'])
                    ? trim((string) $otherArray['bank_account_number'])
                    : '';
                $mergedOther = array_merge($otherArray, [
                    'bank_account_number' => $accountNumber,
                ]);
                if ($declaredBank !== '') {
                    $mergedOther['declared_bank_account_number'] = $declaredBank;
                }

                $formData = [
                    'identification' => is_array($identification) ? $identification : [],
                    'other_information' => $mergedOther,
                    'beneficiary' => is_array($beneficiary) ? $beneficiary : [],
                ];

                $emergencyName = is_array($beneficiary) ? ($beneficiary['emergency_contact_name'] ?? null) : null;
                $emergencyPhone = is_array($beneficiary) ? ($beneficiary['emergency_contact_phone'] ?? null) : null;

                $enrollment = CnssVoluntaryEnrollment::create([
                    'business_unit_id' => $businessUnitId,
                    'agent_id' => $resolvedAgentId,
                    'terminal_id' => $resolvedTerminalId,
                    'client_id' => $client->id,
                    'status' => $status,
                    'voluntary_insured_number' => $numbers['voluntary_insured_number'],
                    'cnss_registration_affiliation_number' => $numbers['cnss_registration_affiliation_number'],
                    'worker_number' => $numbers['worker_number'],
                    'effective_date' => $today,
                    'registration_date' => $today,
                    'last_name' => $identification['last_name'] ?? null,
                    'first_names' => $identification['first_names'] ?? null,
                    'phone' => $identification['phone'] ?? null,
                    'insurance_type_art' => isset($otherArray['insurance_type_art']) ? (string) $otherArray['insurance_type_art'] : null,
                    'form_data' => $formData,
                    'emergency_contact_name' => $emergencyName ? (string) $emergencyName : null,
                    'emergency_contact_phone' => $emergencyPhone ? (string) $emergencyPhone : null,
                ]);

                $idPath = null;
                $idBackPath = null;
                $bcPath = null;
                if ($request->hasFile('id_document')) {
                    $idPath = $request->file('id_document')->store('cnss/enrollments/'.$enrollment->id, 'local');
                }
                if ($request->hasFile('id_document_back')) {
                    $idBackPath = $request->file('id_document_back')->store('cnss/enrollments/'.$enrollment->id, 'local');
                }
                if ($request->hasFile('birth_certificate')) {
                    $bcPath = $request->file('birth_certificate')->store('cnss/enrollments/'.$enrollment->id, 'local');
                }
                if ($idPath !== null || $idBackPath !== null || $bcPath !== null) {
                    $enrollment->fill(array_filter([
                        'id_document_path' => $idPath,
                        'id_document_back_path' => $idBackPath,
                        'birth_certificate_path' => $bcPath,
                    ], fn ($v) => $v !== null));
                    $enrollment->save();
                }

                return $enrollment;
            });

            Log::info('API_CNSS_ENROLLMENT_CREATED', [
                'enrollment_id' => $enrollment->id,
                'business_unit_id' => $businessUnitId,
                'client_id' => $enrollment->client_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Enrollment created successfully',
                'data' => $this->enrollmentPayload($enrollment),
            ], 201);
        } catch (\Exception $e) {
            Log::error('API_CNSS_ENROLLMENT_FAILED', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create enrollment',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Resolve a terminal by serial number (e.g. Android ID stored in `terminals.serial_number`)
     * and the first actively assigned agent.
     *
     * @return array{terminal: Terminal, agent: Agent}|JsonResponse
     */
    protected function resolveEnrollmentTerminalFromSerial(int $businessUnitId, string $serial, string $fieldLabel): array|JsonResponse
    {
        $terminal = Terminal::where('serial_number', $serial)
            ->where('business_unit_id', $businessUnitId)
            ->first();

        if (! $terminal) {
            return response()->json([
                'success' => false,
                'message' => "Terminal with {$fieldLabel} '{$serial}' not found for business unit {$businessUnitId}",
            ], 422);
        }

        $agent = $terminal->agents()->first();
        if (! $agent) {
            return response()->json([
                'success' => false,
                'message' => "Terminal '{$serial}' has no active agent assignment",
            ], 422);
        }

        return ['terminal' => $terminal, 'agent' => $agent];
    }

    public function showEnrollment(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $businessUnitId = (int) $request->query('business_unit_id');
        $enrollment = CnssVoluntaryEnrollment::where('business_unit_id', $businessUnitId)->find($id);

        if (! $enrollment) {
            return response()->json([
                'success' => false,
                'message' => 'Enrollment not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $this->enrollmentPayload($enrollment, includeFormData: true),
        ]);
    }

    public function searchMembers(Request $request): JsonResponse
    {
        $hasBusinessUnitId = $request->filled('business_unit_id');

        $rules = [
            'voluntary_no' => 'nullable|string|max:128',
            'affiliation_no' => 'nullable|string|max:128',
        ];
        if ($hasBusinessUnitId) {
            $rules['business_unit_id'] = 'required|integer|exists:business_units,id';
        }

        $validator = Validator::make($request->query(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (empty($request->query('voluntary_no')) && empty($request->query('affiliation_no'))) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => ['voluntary_no' => ['Either voluntary_no or affiliation_no is required.']],
            ], 422);
        }

        $voluntary = $request->query('voluntary_no');
        $affiliation = $request->query('affiliation_no');

        $query = CnssVoluntaryEnrollment::query();

        if ($hasBusinessUnitId) {
            $query->where('business_unit_id', (int) $request->query('business_unit_id'));
        } else {
            /** @var User $user */
            $user = $request->user();
            $allowedBusinessUnitIds = $this->cnssMemberSearchAllowedBusinessUnitIds($user);
            if ($allowedBusinessUnitIds === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to search CNSS members across business units.',
                ], 403);
            }
            if ($allowedBusinessUnitIds !== null) {
                $query->whereIn('business_unit_id', $allowedBusinessUnitIds);
            }
        }

        if ($voluntary && $affiliation) {
            $query->where(function ($q) use ($voluntary, $affiliation) {
                $q->where('voluntary_insured_number', $voluntary)
                    ->where('cnss_registration_affiliation_number', $affiliation);
            });
        } elseif ($voluntary) {
            $query->where('voluntary_insured_number', $voluntary);
        } else {
            $query->where('cnss_registration_affiliation_number', $affiliation);
        }

        $rows = $query->orderByDesc('id')->limit(25)->get();

        $data = $rows->map(function (CnssVoluntaryEnrollment $e) {
            return [
                'id' => $e->id,
                'business_unit_id' => $e->business_unit_id,
                'client_id' => $e->client_id,
                'ledger_account_number' => data_get($e->form_data, 'other_information.bank_account_number'),
                'voluntary_insured_number' => $e->voluntary_insured_number,
                'cnss_registration_affiliation_number' => $e->cnss_registration_affiliation_number,
                'worker_number' => $e->worker_number,
                'status' => $e->status,
                'last_name' => $e->last_name,
                'first_names' => $e->first_names,
                'phone' => $e->phone,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'ambiguous' => count($data) > 1,
            'data' => $data,
        ]);
    }

    /**
     * Business units the user may search when business_unit_id is omitted.
     * Returns null for super-admin (no restriction).
     *
     * @return list<int>|null
     */
    protected function cnssMemberSearchAllowedBusinessUnitIds(User $user): ?array
    {
        if ($user->isSuperAdmin()) {
            return null;
        }

        $ids = [];
        foreach ($user->businessUnits()->wherePivot('is_active', true)->get() as $businessUnit) {
            if ($user->hasPermission('view-cnss-members', $businessUnit->id)) {
                $ids[] = (int) $businessUnit->id;
            }
        }

        return array_values(array_unique($ids));
    }

    public function storeContribution(StoreCnssContributionRequest $request): JsonResponse
    {
        try {
            $hasBusinessUnitId = $request->filled('business_unit_id');
            $requestedBusinessUnitId = $hasBusinessUnitId ? (int) $request->input('business_unit_id') : null;

            if ($hasBusinessUnitId) {
                $businessUnit = BusinessUnit::find($requestedBusinessUnitId);
                if (! $businessUnit || ! $businessUnit->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Business unit not found or inactive',
                    ], 404);
                }
            }

            $voluntary = $request->input('voluntary_no');
            $affiliation = $request->input('affiliation_no');

            $enrollmentQuery = CnssVoluntaryEnrollment::query();
            if ($hasBusinessUnitId) {
                $enrollmentQuery->where('business_unit_id', $requestedBusinessUnitId);
            }

            if ($voluntary && $affiliation) {
                $enrollmentQuery->where(function ($q) use ($voluntary, $affiliation) {
                    $q->where('voluntary_insured_number', $voluntary)
                        ->where('cnss_registration_affiliation_number', $affiliation);
                });
            } elseif ($voluntary) {
                $enrollmentQuery->where('voluntary_insured_number', $voluntary);
            } else {
                $enrollmentQuery->where('cnss_registration_affiliation_number', $affiliation);
            }

            if (! $hasBusinessUnitId) {
                $preview = (clone $enrollmentQuery)->limit(2)->get();
                if ($preview->count() > 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Multiple CNSS members match this search; include business_unit_id in the request.',
                    ], 422);
                }
                $enrollment = $preview->first();
            } else {
                $enrollment = $enrollmentQuery->first();
            }

            if (! $enrollment) {
                return response()->json([
                    'success' => false,
                    'message' => $hasBusinessUnitId
                        ? 'No matching CNSS member for this business unit'
                        : 'No matching CNSS member',
                ], 422);
            }

            $businessUnitId = (int) $enrollment->business_unit_id;
            $businessUnit = BusinessUnit::find($businessUnitId);
            if (! $businessUnit || ! $businessUnit->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business unit not found or inactive',
                ], 404);
            }

            $mode = $request->input('mode', CnssContribution::MODE_RECORD);
            if (! in_array($mode, [CnssContribution::MODE_RECORD, CnssContribution::MODE_LEDGER], true)) {
                $mode = CnssContribution::MODE_RECORD;
            }

            $reference = $this->makeUniqueContributionReference();
            $amount = (float) $request->input('amount');

            if ($mode === CnssContribution::MODE_LEDGER) {
                return $this->storeLedgerContribution($request, $enrollment, $reference, $amount, $voluntary, $affiliation);
            }

            $contribution = CnssContribution::create([
                'business_unit_id' => $businessUnitId,
                'cnss_voluntary_enrollment_id' => $enrollment->id,
                'agent_id' => $request->input('agent_id'),
                'terminal_id' => null,
                'transaction_id' => null,
                'voluntary_insured_number' => $voluntary,
                'affiliation_number' => $affiliation,
                'amount' => $amount,
                'reference_period' => $request->input('reference_period'),
                'payment_frequency' => $request->input('payment_frequency'),
                'mode' => CnssContribution::MODE_RECORD,
                'reference' => $reference,
                'metadata' => $this->contributionMetadata($request, $enrollment),
            ]);

            Log::info('API_CNSS_CONTRIBUTION_RECORD', [
                'contribution_id' => $contribution->id,
                'business_unit_id' => $businessUnitId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contribution recorded successfully',
                'data' => $this->contributionPayload($contribution),
            ], 201);
        } catch (\Exception $e) {
            Log::error('API_CNSS_CONTRIBUTION_FAILED', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to record contribution',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    protected function storeLedgerContribution(
        StoreCnssContributionRequest $request,
        CnssVoluntaryEnrollment $enrollment,
        string $reference,
        float $amount,
        mixed $voluntary,
        mixed $affiliation
    ): JsonResponse {
        $businessUnitId = (int) $enrollment->business_unit_id;
        $pin = $request->input('pin');
        $deviceSerial = $request->input('device_serial');

        $terminal = Terminal::where('serial_number', $deviceSerial)
            ->where('business_unit_id', $businessUnitId)
            ->first();

        if (! $terminal) {
            return response()->json([
                'success' => false,
                'message' => "Terminal with device_serial '{$deviceSerial}' not found for business unit {$businessUnitId}",
            ], 422);
        }

        $agent = $terminal->agents()->first();
        if (! $agent) {
            return response()->json([
                'success' => false,
                'message' => "Terminal '{$deviceSerial}' has no active agent assignment",
            ], 422);
        }

        if (! Hash::check($pin, $agent->password)) {
            Log::warning('API_CNSS_LEDGER_INVALID_PIN', [
                'agent_id' => $agent->id,
                'business_unit_id' => $businessUnitId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid PIN',
            ], 422);
        }

        $accountNumber = $request->input('account_number') ?: config('cnss.default_ledger_account_number');
        if (! $accountNumber) {
            $accountNumber = trim((string) data_get($enrollment->form_data, 'other_information.bank_account_number'));
        }
        if (! $accountNumber) {
            return response()->json([
                'success' => false,
                'message' => 'account_number is required for ledger mode (set on the request, CNSS_DEFAULT_LEDGER_ACCOUNT_NUMBER, or enrollment other_information.bank_account_number).',
            ], 422);
        }

        $account = Account::where('account_number', $accountNumber)
            ->where('business_unit_id', $businessUnitId)
            ->first();

        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => "Account with number '{$accountNumber}' not found for business unit {$businessUnitId}",
            ], 422);
        }

        if (! $account->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is not active',
            ], 422);
        }

        $transactionTypeCode = (string) config('cnss.ledger_transaction_type_code', 'CNSS001');
        $transactionType = TransactionType::where('code', $transactionTypeCode)
            ->where(function ($query) use ($businessUnitId) {
                $query->where(function ($q) use ($businessUnitId) {
                    $q->whereNull('business_unit_id')
                        ->whereHas('businessUnits', function ($q2) use ($businessUnitId) {
                            $q2->where('business_units.id', $businessUnitId);
                        });
                })
                    ->orWhere('business_unit_id', $businessUnitId);
            })
            ->where('is_active', true)
            ->first();

        if (! $transactionType) {
            return response()->json([
                'success' => false,
                'message' => "Transaction type with code '{$transactionTypeCode}' not found or not assigned to business unit {$businessUnitId}",
            ], 422);
        }

        $calculation = $this->calculationService->calculateSplit(
            $transactionType->id,
            $amount,
            $businessUnitId
        );

        [$contribution, $transaction] = DB::transaction(function () use (
            $request,
            $enrollment,
            $reference,
            $amount,
            $voluntary,
            $affiliation,
            $businessUnitId,
            $agent,
            $terminal,
            $accountNumber,
            $transactionType,
            $calculation
        ) {
            $contribution = CnssContribution::create([
                'business_unit_id' => $businessUnitId,
                'cnss_voluntary_enrollment_id' => $enrollment->id,
                'agent_id' => $agent->id,
                'terminal_id' => $terminal->id,
                'transaction_id' => null,
                'voluntary_insured_number' => $voluntary,
                'affiliation_number' => $affiliation,
                'amount' => $amount,
                'reference_period' => $request->input('reference_period'),
                'payment_frequency' => $request->input('payment_frequency'),
                'mode' => CnssContribution::MODE_LEDGER,
                'reference' => $reference,
                'metadata' => $this->contributionMetadata($request, $enrollment),
            ]);

            $txMeta = array_merge(
                (array) $request->input('metadata', []),
                [
                    'cnss_contribution_id' => $contribution->id,
                    'voluntary_insured_number' => $voluntary,
                    'affiliation_number' => $affiliation,
                    'reference_period' => $request->input('reference_period'),
                    'payment_frequency' => $request->input('payment_frequency'),
                ]
            );

            $transaction = Transaction::create([
                'business_unit_id' => $businessUnitId,
                'transaction_type_id' => $transactionType->id,
                'agent_id' => $agent->id,
                'terminal_id' => $terminal->id,
                'client_id' => null,
                'card_number' => null,
                'account_number' => $accountNumber,
                'transaction_amount' => $amount,
                'transaction_cost' => $calculation['transaction_cost'],
                'commission' => $calculation['commission'],
                'business_share' => $calculation['business_share'],
                'transaction_date' => $request->input('transaction_date') ?? now(),
                'status' => $request->input('status', 'completed'),
                'reference' => $request->input('transaction_reference'),
                'metadata' => $txMeta,
                'created_by' => null,
            ]);

            $contribution->transaction_id = $transaction->id;
            $contribution->save();

            return [$contribution, $transaction];
        });

        Log::info('API_CNSS_CONTRIBUTION_LEDGER', [
            'contribution_id' => $contribution->id,
            'transaction_id' => $transaction->id,
            'business_unit_id' => $businessUnitId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contribution and transaction created successfully',
            'data' => array_merge(
                $this->contributionPayload($contribution),
                ['transaction' => $this->transactionSubset($transaction, $deviceSerial, $transactionTypeCode)]
            ),
        ], 201);
    }

    protected function transactionSubset(Transaction $transaction, string $deviceSerial, string $transactionTypeCode): array
    {
        return [
            'id' => $transaction->id,
            'business_unit_id' => $transaction->business_unit_id,
            'account_number' => $transaction->account_number,
            'transaction_amount' => $transaction->transaction_amount,
            'transaction_cost' => $transaction->transaction_cost,
            'commission' => $transaction->commission,
            'business_share' => $transaction->business_share,
            'transaction_date' => $transaction->transaction_date->format('Y-m-d H:i:s'),
            'status' => $transaction->status,
            'reference' => $transaction->reference,
            'transaction_type_code' => $transactionTypeCode,
            'device_serial' => $deviceSerial,
            'agent_id' => $transaction->agent_id,
            'terminal_id' => $transaction->terminal_id,
        ];
    }

    protected function contributionMetadata(StoreCnssContributionRequest $request, CnssVoluntaryEnrollment $enrollment): array
    {
        return array_merge(
            [
                'cnss_voluntary_enrollment_id' => $enrollment->id,
                'voluntary_insured_number' => $enrollment->voluntary_insured_number,
                'cnss_registration_affiliation_number' => $enrollment->cnss_registration_affiliation_number,
            ],
            (array) $request->input('metadata', [])
        );
    }

    protected function contributionPayload(CnssContribution $c): array
    {
        return [
            'id' => $c->id,
            'reference' => $c->reference,
            'mode' => $c->mode,
            'amount' => $c->amount,
            'reference_period' => $c->reference_period,
            'payment_frequency' => $c->payment_frequency,
            'cnss_voluntary_enrollment_id' => $c->cnss_voluntary_enrollment_id,
        ];
    }

    protected function enrollmentPayload(CnssVoluntaryEnrollment $e, bool $includeFormData = false): array
    {
        $data = [
            'id' => $e->id,
            'client_id' => $e->client_id,
            'ledger_account_number' => data_get($e->form_data, 'other_information.bank_account_number'),
            'declared_bank_account_number' => data_get($e->form_data, 'other_information.declared_bank_account_number'),
            'voluntary_insured_number' => $e->voluntary_insured_number,
            'cnss_registration_affiliation_number' => $e->cnss_registration_affiliation_number,
            'worker_number' => $e->worker_number,
            'effective_date' => $e->effective_date?->format('Y-m-d'),
            'registration_date' => $e->registration_date?->format('Y-m-d'),
            'status' => $e->status,
            'emergency_contact_name' => $e->emergency_contact_name,
            'emergency_contact_phone' => $e->emergency_contact_phone,
            'id_document_path' => $e->id_document_path,
            'id_document_back_path' => $e->id_document_back_path,
            'birth_certificate_path' => $e->birth_certificate_path,
        ];

        if ($includeFormData) {
            $data['form_data'] = $e->form_data;
        }

        return $data;
    }

    protected function makeUniqueContributionReference(): string
    {
        do {
            $ref = 'CNSS-REC-'.Str::upper(Str::random(10));
        } while (CnssContribution::where('reference', $ref)->exists());

        return $ref;
    }
}
