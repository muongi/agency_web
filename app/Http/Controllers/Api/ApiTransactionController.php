<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InternalLedgerPostingException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\Card;
use App\Models\Client;
use App\Models\Terminal;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Services\Cbs\CbsGateway;
use App\Services\InternalLedgerPostingService;
use App\Services\KatoFeePayloadBuilder;
use App\Services\KatoService;
use App\Services\TransactionCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ApiTransactionController extends Controller
{
    protected $calculationService;

    protected InternalLedgerPostingService $internalLedgerPostingService;

    protected CbsGateway $cbsGateway;

    protected KatoFeePayloadBuilder $katoFeePayloadBuilder;

    public function __construct(
        TransactionCalculationService $calculationService,
        InternalLedgerPostingService $internalLedgerPostingService,
        CbsGateway $cbsGateway,
        KatoFeePayloadBuilder $katoFeePayloadBuilder
    ) {
        $this->calculationService = $calculationService;
        $this->internalLedgerPostingService = $internalLedgerPostingService;
        $this->cbsGateway = $cbsGateway;
        $this->katoFeePayloadBuilder = $katoFeePayloadBuilder;
    }

    /**
     * Store a newly created transaction via API.
     *
     * New-style request (primary):
     * - business_unit_id, card_number, pin, otp, amount, transaction_type_code, device_serial
     *
     * Old-style request (backward compatibility):
     * - business_unit_id, transaction_type_id, transaction_amount, agent_id, terminal_id
     */
    public function store(Request $request)
    {
        Log::info('Transaction API store start', [
            'path' => $request->path(),
            'has_card_number' => $request->filled('card_number'),
            'transaction_type_code' => $request->input('transaction_type_code'),
            'has_otp' => $request->filled('otp'),
        ]);

        try {
            // Determine if new-style or old-style request
            $isNewStyle = $request->has('card_number') && $request->has('transaction_type_code') && $request->has('device_serial');

            if ($isNewStyle) {
                return $this->handleNewStyleRequest($request);
            } else {
                return $this->handleOldStyleRequest($request);
            }
        } catch (\Exception $e) {
            Log::error('API Transaction Creation Failed: '.$e->getMessage(), [
                'request' => $request->except(['pin', 'otp']), // Don't log sensitive data
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create transaction',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    protected function resolveCustomerAccount(int $businessUnitId, string $accountNumber): ?Account
    {
        return Account::query()
            ->where('business_unit_id', $businessUnitId)
            ->where('account_number', $accountNumber)
            ->where('accountable_type', Client::class)
            ->first();
    }

    protected function resolveKatoCustomerAccount(
        int $businessUnitId,
        ?string $accountNumber,
        ?string $memberNo,
        ?string $accountCode
    ): ?Account {
        if ($accountNumber) {
            return $this->resolveCustomerAccount($businessUnitId, $accountNumber);
        }

        if ($memberNo && $accountCode) {
            $client = Client::where('business_unit_id', $businessUnitId)
                ->where('kato_member_no', $memberNo)
                ->first();

            if (! $client) {
                return null;
            }

            return $client->accounts()
                ->where('account_type', 'general')
                ->where('kato_account_code', $accountCode)
                ->first();
        }

        return null;
    }

    /**
     * @return array{payload_type: string, account_number: ?string, member_no: ?string, account_code: ?string}
     */
    protected function katoTransactionPayloadMeta(Account $account, Client $client, string $payloadType = 'accountNumber'): array
    {
        return [
            'payload_type' => $payloadType,
            'account_number' => $account->account_number,
            'member_no' => $client->kato_member_no,
            'account_code' => $account->kato_account_code,
        ];
    }

    protected function resolveKatoAgentAccountNumberOrFail(Agent $agent): ?JsonResponse
    {
        $agentAccountNumber = $agent->katoFloatAccountNumber();

        if ($agentAccountNumber) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Agent KATO float account is not synced. Re-register the agent with KATO or run kato:backfill-agent-accounts.',
        ], 422);
    }

    /**
     * @param  array{transaction_cost: float, commission: float, business_share: float}  $calculation
     * @return array<string, mixed>
     */
    protected function buildKatoDepositPayload(
        Agent $agent,
        Account $customerAccount,
        float $amount,
        array $calculation
    ): array {
        $base = [
            'agentAccountNumber' => (string) $agent->katoFloatAccountNumber(),
            'accountNumber' => $customerAccount->account_number,
            'amount' => $amount,
        ];

        return $this->katoFeePayloadBuilder->build(
            KatoFeePayloadBuilder::OPERATION_DEPOSIT,
            $calculation,
            $base
        );
    }

    /**
     * @param  array{transaction_cost: float, commission: float, business_share: float}  $calculation
     * @return array<string, mixed>
     */
    protected function buildKatoWithdrawPayload(
        Agent $agent,
        Account $customerAccount,
        float $amount,
        ?string $memberPin,
        array $calculation
    ): array {
        $base = [
            'agentAccountNumber' => (string) $agent->katoFloatAccountNumber(),
            'accountNumber' => $customerAccount->account_number,
            'amount' => $amount,
        ];

        // Biometric withdraw may omit PIN; card/PIN flows still pass memberPin.
        if ($memberPin !== null && $memberPin !== '') {
            $base['memberPin'] = $memberPin;
        }

        return $this->katoFeePayloadBuilder->build(
            KatoFeePayloadBuilder::OPERATION_WITHDRAW,
            $calculation,
            $base
        );
    }

    /**
     * @param  array{transaction_cost: float, commission: float, business_share: float}  $calculation
     * @return array<string, mixed>
     */
    protected function buildKatoEnquiryPayload(
        Agent $agent,
        Account $customerAccount,
        array $calculation
    ): array {
        $base = [
            'agentAccountNumber' => (string) $agent->katoFloatAccountNumber(),
            'accountNumber' => $customerAccount->account_number,
        ];

        return $this->katoFeePayloadBuilder->build(
            KatoFeePayloadBuilder::OPERATION_ENQUIRY,
            $calculation,
            $base
        );
    }

    protected function isCashWithdrawalTransaction(TransactionType $transactionType): bool
    {
        return $transactionType->internal_ledger_flow === TransactionType::INTERNAL_LEDGER_FLOW_CASH_WITHDRAWAL
            || $transactionType->code === '0110000';
    }

    protected function refreshLocalBalancesFromKato(BusinessUnit $businessUnit, Client $client): void
    {
        try {
            $katoService = new KatoService();
            $result = $katoService->getMemberBalances($businessUnit, (string) $client->kato_member_no);
            if ($result['success'] && ! empty($result['data']['balances'])) {
                $katoService->syncBalancesToLocalAccounts($client, $result['data']['balances']);
            }
        } catch (\Throwable $e) {
            Log::warning('KATO balance refresh after transaction failed', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function resolveTerminalOrFail(int $businessUnitId, string $deviceSerial): Terminal
    {
        $serial = trim($deviceSerial);
        $registered = Terminal::where('business_unit_id', $businessUnitId)
            ->orderBy('id')
            ->pluck('serial_number')
            ->all();

        Log::info('TX_TERMINAL_LOOKUP', [
            'received_serial' => $serial,
            'business_unit_id' => $businessUnitId,
            'registered_serials' => $registered,
        ]);

        $terminal = Terminal::where('business_unit_id', $businessUnitId)
            ->where(function ($q) use ($serial) {
                $q->where('serial_number', $serial)
                    ->orWhereRaw('LOWER(serial_number) = ?', [strtolower($serial)]);
            })
            ->first();

        if (! $terminal) {
            Log::warning('TX_TERMINAL_NOT_FOUND', [
                'received_serial' => $serial,
                'business_unit_id' => $businessUnitId,
                'registered_serials' => $registered,
            ]);

            throw new \InvalidArgumentException(
                "Terminal with device_serial '{$serial}' not found for business unit {$businessUnitId}"
            );
        }

        return $terminal;
    }

    protected function resolveTransactionTypeOrFail(int $businessUnitId, string $transactionTypeCode): TransactionType
    {
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
            throw new \InvalidArgumentException(
                "Transaction type with code '{$transactionTypeCode}' not found or not assigned to business unit {$businessUnitId}"
            );
        }

        return $transactionType;
    }

    /**
     * Post to configurable CBS when a mapping exists for this transaction type.
     *
     * @return array{synced: bool, metadata: array<string, mixed>}|JsonResponse|null
     */
    protected function attemptConfigurableCbsPost(
        BusinessUnit $businessUnit,
        TransactionType $transactionType,
        Request $request,
        Account $account,
        ?Client $client,
        ?Agent $agent,
        float $amount,
        ?string $reference = null
    ): array|JsonResponse|null {
        $result = $this->cbsGateway->executeIfMapped($businessUnit, $transactionType, [
            'transaction' => [
                'amount' => $amount,
                'reference' => $reference,
                'type_code' => $transactionType->code,
            ],
            'account' => $account,
            'client' => $client,
            'agent' => $agent,
            'request' => $request->only(['pin', 'otp']),
        ]);

        if ($result === null) {
            return null;
        }

        if (! $result['success']) {
            Log::info('Transaction API configurable CBS failed', [
                'business_unit_id' => $businessUnit->id,
                'transaction_type_id' => $transactionType->id,
                'error' => $result['error'] ?? 'unknown',
            ]);

            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Core banking transaction failed',
            ], 422);
        }

        $metadata = [
            'cbs_api_response' => $result['full_response'] ?? null,
            'cbs_transaction_id' => $result['data']['transactionId'] ?? null,
        ];

        return [
            'synced' => true,
            'metadata' => array_filter($metadata, fn ($value) => $value !== null),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @param  array<string, mixed>  $cbsMetadata
     * @return array<string, mixed>|null
     */
    protected function mergeCbsMetadata(?array $metadata, array $cbsMetadata): ?array
    {
        $metadata = is_array($metadata) ? $metadata : [];

        return array_merge($metadata, $cbsMetadata);
    }

    public function biometricWithdraw(Request $request): JsonResponse
    {
        Log::info('Transaction API biometricWithdraw start', [
            'path' => $request->path(),
            'has_account_number' => $request->filled('account_number'),
            'has_member_no' => $request->filled('member_no'),
            'has_pin' => $request->filled('pin'),
        ]);

        $validator = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'account_number' => 'required_without_all:member_no,account_code|nullable|string|max:255',
            'member_no' => 'required_with:account_code|nullable|string|max:50',
            'account_code' => 'required_with:member_no|nullable|string|max:20',
            'amount' => 'required|numeric|min:0.01',
            'transaction_type_code' => 'required|string|max:50',
            'device_serial' => 'required|string|max:255',
            'verification_method' => 'required|string|max:32',
            'reference' => 'required|string|max:255|unique:transactions,reference',
            // Required only when posting withdrawal to KATO (member PIN).
            'pin' => 'nullable|string|min:4|max:255',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            Log::info('Transaction API biometricWithdraw validation_failed', [
                'errors' => $validator->errors()->toArray(),
                'expected_payload' => [
                    'business_unit_id' => 'integer, required',
                    'account_number' => 'string, required unless member_no+account_code',
                    'amount' => 'numeric, required',
                    'transaction_type_code' => 'string, required',
                    'device_serial' => 'string, required',
                    'verification_method' => 'string, required',
                    'reference' => 'string, required, unique',
                    'pin' => 'string, required for KATO withdrawal',
                ],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $businessUnitId = (int) $request->input('business_unit_id');
        $accountNumber = $request->filled('account_number') ? (string) $request->input('account_number') : null;
        $memberNo = $request->filled('member_no') ? (string) $request->input('member_no') : null;
        $accountCode = $request->filled('account_code') ? (string) $request->input('account_code') : null;
        $amount = (float) $request->input('amount');
        $transactionTypeCode = (string) $request->input('transaction_type_code');
        $deviceSerial = (string) $request->input('device_serial');
        $reference = (string) $request->input('reference');
        $memberPin = $request->filled('pin') ? (string) $request->input('pin') : null;

        Log::info('Transaction API biometricWithdraw validated', [
            'business_unit_id' => $businessUnitId,
            'account_number' => $accountNumber,
            'amount' => $amount,
            'device_serial' => $deviceSerial,
            'reference' => $reference,
        ]);

        try {
            $businessUnit = BusinessUnit::find($businessUnitId);
            if (! $businessUnit || ! $businessUnit->is_active) {
                Log::info('Transaction API biometricWithdraw business_unit_invalid', [
                    'business_unit_id' => $businessUnitId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Business unit not found or inactive',
                ], 404);
            }

            $customerAccount = $this->resolveKatoCustomerAccount(
                $businessUnitId,
                $accountNumber,
                $memberNo,
                $accountCode
            );
            if (! $customerAccount) {
                Log::info('Transaction API biometricWithdraw account_not_found', [
                    'business_unit_id' => $businessUnitId,
                    'account_number' => $accountNumber,
                    'member_no' => $memberNo,
                    'account_code' => $accountCode,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Client account not found for internal ledger posting.',
                ], 422);
            }

            $terminal = $this->resolveTerminalOrFail($businessUnitId, $deviceSerial);
            $agent = $terminal->agents()->first();
            if (! $agent) {
                Log::info('Transaction API biometricWithdraw no_agent_on_terminal', [
                    'device_serial' => $deviceSerial,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Terminal '{$deviceSerial}' has no active agent assignment",
                ], 422);
            }

            $transactionType = $this->resolveTransactionTypeOrFail($businessUnitId, $transactionTypeCode);
            $calculation = $this->calculationService->calculateSplit(
                $transactionType->id,
                $amount,
                $businessUnitId
            );

            $isKato = false;
            $katoTransactionId = null;
            $katoApiResponse = null;
            $metadata = $request->input('metadata');

            $clientForKatoCheck = $customerAccount->accountable;
            Log::info('Transaction API biometricWithdraw kato_check', [
                'business_unit_id' => $businessUnitId,
                'is_kato_bu' => $businessUnit->isKatoEnabled(),
                'client_kato_member_no' => $clientForKatoCheck?->kato_member_no ? 'present' : 'missing',
            ]);

            // KATO-first: post to core banking before creating local transaction record.
            if ($businessUnit->isKatoEnabled()) {
                $client = $customerAccount->accountable;
                if (! $client || ! $client->kato_member_no) {
                    Log::info('Transaction API biometricWithdraw client_not_on_kato', [
                        'client_id' => $customerAccount->accountable_id,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Client is not registered with KATO yet',
                    ], 422);
                }

                // Optional PIN: if provided, verify against active card; biometric path may omit PIN.
                if ($memberPin) {
                    $card = Card::query()
                        ->where('business_unit_id', $businessUnitId)
                        ->where('account_id', $customerAccount->id)
                        ->active()
                        ->first();

                    if (! $card || ! $card->verifyPin($memberPin)) {
                        Log::info('Transaction API biometricWithdraw invalid_member_pin', [
                            'card_id' => $card?->id,
                            'has_card' => (bool) $card,
                        ]);

                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid member PIN',
                        ], 422);
                    }
                }

                if ($agentError = $this->resolveKatoAgentAccountNumberOrFail($agent)) {
                    return $agentError;
                }

                $katoPayload = $this->buildKatoWithdrawPayload(
                    $agent,
                    $customerAccount,
                    $amount,
                    $memberPin,
                    $calculation
                );
                $katoRequestLog = $katoPayload;
                if (array_key_exists('memberPin', $katoRequestLog)) {
                    $katoRequestLog['memberPin'] = '[REDACTED]';
                }

                Log::info('Transaction API biometricWithdraw kato_calling', [
                    'business_unit_id' => $businessUnitId,
                    'kato_request' => $katoRequestLog,
                    'grep_hint' => 'grep "KATO API" storage/logs/laravel.log',
                ]);

                $katoService = new KatoService();
                $katoResult = $katoService->withdraw($businessUnit, $katoPayload);
                if (! $katoResult['success']) {
                    Log::info('Transaction API biometricWithdraw kato_failed', [
                        'error' => $katoResult['error'] ?? 'unknown',
                        'http_status' => $katoResult['http_status'] ?? null,
                        'kato_full_response' => $katoResult['full_response'] ?? null,
                        'kato_request' => $katoRequestLog,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $katoResult['error'] ?? 'KATO withdrawal failed',
                    ], 422);
                }

                Log::info('Transaction API biometricWithdraw kato_success', [
                    'business_unit_id' => $businessUnitId,
                    'kato_transaction_id' => $katoResult['data']['transactionId'] ?? null,
                    'kato_request' => $katoRequestLog,
                ]);

                $isKato = true;
                $katoTransactionId = $katoResult['data']['transactionId'] ?? null;
                $katoApiResponse = $katoResult['full_response'] ?? null;

                if ($katoApiResponse !== null) {
                    $metadata = is_array($metadata) ? $metadata : [];
                    $metadata['kato_api_response'] = $katoApiResponse;
                    $metadata['kato_payload'] = $katoPayload;
                }

                $this->refreshLocalBalancesFromKato($businessUnit, $client);
            } else {
                $businessUnit->loadMissing('coreBankingSystem');
                Log::info('Transaction API biometricWithdraw kato_skipped', [
                    'business_unit_id' => $businessUnitId,
                    'reason' => 'not_kato_core_banking',
                    'cbs_code' => $businessUnit->coreBankingSystem?->code,
                    'has_kato_company_id' => (bool) $businessUnit->kato_company_id,
                ]);
            }

            if (! $isKato) {
                $cbsOutcome = $this->attemptConfigurableCbsPost(
                    $businessUnit,
                    $transactionType,
                    $request,
                    $customerAccount,
                    $customerAccount->accountable,
                    $agent,
                    $amount,
                    $reference
                );
                if ($cbsOutcome instanceof JsonResponse) {
                    return $cbsOutcome;
                }
                if (is_array($cbsOutcome) && ($cbsOutcome['synced'] ?? false)) {
                    $metadata = $this->mergeCbsMetadata($metadata, $cbsOutcome['metadata'] ?? []);
                }
            }

            $transactionId = null;
            DB::transaction(function () use (
                &$transactionId,
                $businessUnitId,
                $customerAccount,
                $agent,
                $terminal,
                $transactionType,
                $amount,
                $calculation,
                $reference,
                $metadata,
                $isKato,
                $katoTransactionId
            ) {
                $transaction = Transaction::create([
                    'business_unit_id' => $businessUnitId,
                    'transaction_type_id' => $transactionType->id,
                    'agent_id' => $agent->id,
                    'terminal_id' => $terminal->id,
                    'client_id' => $customerAccount->accountable_id,
                    'account_number' => $customerAccount->account_number,
                    'transaction_amount' => $amount,
                    'transaction_cost' => $calculation['transaction_cost'],
                    'commission' => $calculation['commission'],
                    'business_share' => $calculation['business_share'],
                    'transaction_date' => now(),
                    'status' => 'completed',
                    'reference' => $reference,
                    'metadata' => $metadata,
                    'created_by' => null,
                    'kato_transaction_id' => $isKato ? $katoTransactionId : null,
                    'kato_synced_at' => $isKato ? now() : null,
                ]);

                $transactionId = $transaction->id;
                $this->internalLedgerPostingService->postForNewTransaction($transaction);
            });

            $tx = Transaction::findOrFail($transactionId);

            Log::info('Transaction API biometricWithdraw success', [
                'transaction_id' => $tx->id,
                'kato' => $isKato,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Biometric withdrawal processed successfully',
                'data' => [
                    'id' => $tx->id,
                ],
            ], 201);
        } catch (InternalLedgerPostingException $e) {
            Log::error('Transaction API biometricWithdraw ledger_error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\InvalidArgumentException $e) {
            Log::info('Transaction API biometricWithdraw invalid_argument', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function cashDeposit(Request $request): JsonResponse
    {
        Log::info('Transaction API cashDeposit start', [
            'path' => $request->path(),
            'agent_id' => $request->input('agent_id'),
            'has_account_number' => $request->filled('account_number'),
            'has_pin' => $request->filled('pin'),
        ]);

        $validator = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'agent_id' => 'required|integer|exists:agents,id',
            'pin' => 'required|string|min:4|max:255',
            'account_number' => 'required_without_all:member_no,account_code|nullable|string|max:255',
            'member_no' => 'required_with:account_code|nullable|string|max:50',
            'account_code' => 'required_with:member_no|nullable|string|max:20',
            'amount' => 'required|numeric|min:0.01',
            'transaction_type_code' => 'required|string|max:50',
            'device_serial' => 'required|string|max:255',
            'reference' => 'required|string|max:255|unique:transactions,reference',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            Log::info('Transaction API cashDeposit validation_failed', [
                'errors' => $validator->errors()->toArray(),
                'expected_payload' => [
                    'business_unit_id' => 'integer, required',
                    'agent_id' => 'integer, required',
                    'pin' => 'string, required (agent PIN)',
                    'account_number' => 'string, required unless member_no+account_code',
                    'amount' => 'numeric, required',
                    'transaction_type_code' => 'string, required',
                    'device_serial' => 'string, required',
                    'reference' => 'string, required, unique',
                ],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $businessUnitId = (int) $request->input('business_unit_id');
        $agentId = (int) $request->input('agent_id');
        $pin = (string) $request->input('pin');
        $accountNumber = $request->filled('account_number') ? (string) $request->input('account_number') : null;
        $memberNo = $request->filled('member_no') ? (string) $request->input('member_no') : null;
        $accountCode = $request->filled('account_code') ? (string) $request->input('account_code') : null;
        $amount = (float) $request->input('amount');
        $transactionTypeCode = (string) $request->input('transaction_type_code');
        $deviceSerial = (string) $request->input('device_serial');
        $reference = (string) $request->input('reference');

        Log::info('Transaction API cashDeposit validated', [
            'business_unit_id' => $businessUnitId,
            'agent_id' => $agentId,
            'account_number' => $accountNumber,
            'amount' => $amount,
            'device_serial' => $deviceSerial,
            'reference' => $reference,
        ]);

        try {
            $businessUnit = BusinessUnit::find($businessUnitId);
            if (! $businessUnit || ! $businessUnit->is_active) {
                Log::info('Transaction API cashDeposit business_unit_invalid', [
                    'business_unit_id' => $businessUnitId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Business unit not found or inactive',
                ], 404);
            }

            $agent = Agent::where('business_unit_id', $businessUnitId)->find($agentId);
            if (! $agent) {
                Log::info('Transaction API cashDeposit agent_not_found', [
                    'agent_id' => $agentId,
                    'business_unit_id' => $businessUnitId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Agent with id '{$agentId}' not found for business unit {$businessUnitId}",
                ], 422);
            }

            if (! Hash::check($pin, $agent->password)) {
                Log::info('Transaction API cashDeposit invalid_agent_pin', [
                    'agent_id' => $agentId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid PIN',
                ], 422);
            }

            $customerAccount = $this->resolveKatoCustomerAccount(
                $businessUnitId,
                $accountNumber,
                $memberNo,
                $accountCode
            );
            if (! $customerAccount) {
                Log::info('Transaction API cashDeposit account_not_found', [
                    'business_unit_id' => $businessUnitId,
                    'account_number' => $accountNumber,
                    'member_no' => $memberNo,
                    'account_code' => $accountCode,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Client account not found for internal ledger posting.',
                ], 422);
            }

            $terminal = $this->resolveTerminalOrFail($businessUnitId, $deviceSerial);

            $isAssignedToTerminal = $terminal->agents()->where('agents.id', $agent->id)->exists();
            if (! $isAssignedToTerminal) {
                Log::info('Transaction API cashDeposit agent_not_on_terminal', [
                    'agent_id' => $agent->id,
                    'device_serial' => $deviceSerial,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Agent '{$agent->id}' is not actively assigned to terminal '{$deviceSerial}'",
                ], 422);
            }

            $transactionType = $this->resolveTransactionTypeOrFail($businessUnitId, $transactionTypeCode);
            $calculation = $this->calculationService->calculateSplit(
                $transactionType->id,
                $amount,
                $businessUnitId
            );

            $isKato = false;
            $katoTransactionId = null;
            $katoApiResponse = null;
            $metadata = $request->input('metadata');

            // KATO-first: post to core banking before creating local transaction record.
            if ($businessUnit->isKatoEnabled()) {
                $client = $customerAccount->accountable;
                if (! $client || ! $client->kato_member_no) {
                    Log::info('Transaction API cashDeposit client_not_on_kato', [
                        'client_id' => $customerAccount->accountable_id,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Client is not registered with KATO yet',
                    ], 422);
                }

                if ($agentError = $this->resolveKatoAgentAccountNumberOrFail($agent)) {
                    return $agentError;
                }

                $katoPayload = $this->buildKatoDepositPayload(
                    $agent,
                    $customerAccount,
                    $amount,
                    $calculation
                );

                $katoService = new KatoService();
                $katoResult = $katoService->deposit($businessUnit, $katoPayload);
                if (! $katoResult['success']) {
                    Log::info('Transaction API cashDeposit kato_failed', [
                        'error' => $katoResult['error'] ?? 'unknown',
                        'http_status' => $katoResult['http_status'] ?? null,
                        'kato_full_response' => $katoResult['full_response'] ?? null,
                        'kato_request' => $katoPayload,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $katoResult['error'] ?? 'KATO deposit failed',
                    ], 422);
                }

                $isKato = true;
                $katoTransactionId = $katoResult['data']['transactionId'] ?? null;
                $katoApiResponse = $katoResult['full_response'] ?? null;

                if ($katoApiResponse !== null) {
                    $metadata = is_array($metadata) ? $metadata : [];
                    $metadata['kato_api_response'] = $katoApiResponse;
                    $metadata['kato_payload'] = $katoPayload;
                }

                $this->refreshLocalBalancesFromKato($businessUnit, $client);
            } elseif ($businessUnit->hasConfigurableCbs()) {
                $cbsOutcome = $this->attemptConfigurableCbsPost(
                    $businessUnit,
                    $transactionType,
                    $request,
                    $customerAccount,
                    $customerAccount->accountable,
                    $agent,
                    $amount,
                    $reference
                );
                if ($cbsOutcome instanceof JsonResponse) {
                    return $cbsOutcome;
                }
                if (is_array($cbsOutcome) && ($cbsOutcome['synced'] ?? false)) {
                    $metadata = $this->mergeCbsMetadata($metadata, $cbsOutcome['metadata'] ?? []);
                }
            }

            $transactionId = null;
            DB::transaction(function () use (
                &$transactionId,
                $businessUnitId,
                $customerAccount,
                $agent,
                $terminal,
                $transactionType,
                $amount,
                $calculation,
                $reference,
                $metadata,
                $isKato,
                $katoTransactionId
            ) {
                $transaction = Transaction::create([
                    'business_unit_id' => $businessUnitId,
                    'transaction_type_id' => $transactionType->id,
                    'agent_id' => $agent->id,
                    'terminal_id' => $terminal->id,
                    'client_id' => $customerAccount->accountable_id,
                    'account_number' => $customerAccount->account_number,
                    'transaction_amount' => $amount,
                    'transaction_cost' => $calculation['transaction_cost'],
                    'commission' => $calculation['commission'],
                    'business_share' => $calculation['business_share'],
                    'transaction_date' => now(),
                    'status' => 'completed',
                    'reference' => $reference,
                    'metadata' => $metadata,
                    'created_by' => null,
                    'kato_transaction_id' => $isKato ? $katoTransactionId : null,
                    'kato_synced_at' => $isKato ? now() : null,
                ]);

                $transactionId = $transaction->id;
                $this->internalLedgerPostingService->postForNewTransaction($transaction);
            });

            $tx = Transaction::findOrFail($transactionId);

            Log::info('Transaction API cashDeposit success', [
                'transaction_id' => $tx->id,
                'kato' => $isKato,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cash deposit processed successfully',
                'data' => [
                    'id' => $tx->id,
                ],
            ], 201);
        } catch (InternalLedgerPostingException $e) {
            Log::error('Transaction API cashDeposit ledger_error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\InvalidArgumentException $e) {
            Log::info('Transaction API cashDeposit invalid_argument', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function balanceEnquiry(Request $request): JsonResponse
    {
        return $this->processKatoEnquiry($request, 'balance-enquiry', '310000');
    }

    public function miniStatement(Request $request): JsonResponse
    {
        return $this->processKatoEnquiry($request, 'mini-statement', '320000');
    }

    protected function processKatoEnquiry(Request $request, string $operation, string $defaultTypeCode): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'agent_id' => 'required|integer|exists:agents,id',
            'account_number' => 'required|string|max:255',
            'transaction_type_code' => 'nullable|string|max:50',
            'device_serial' => 'required|string|max:255',
            'reference' => 'required|string|max:255|unique:transactions,reference',
            'limit' => 'nullable|integer|min:1|max:50',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $businessUnitId = (int) $request->input('business_unit_id');
        $agentId = (int) $request->input('agent_id');
        $accountNumber = (string) $request->input('account_number');
        $transactionTypeCode = (string) ($request->input('transaction_type_code') ?: $defaultTypeCode);
        $deviceSerial = (string) $request->input('device_serial');
        $reference = (string) $request->input('reference');
        $limit = (int) ($request->input('limit', 5));

        try {
            $businessUnit = BusinessUnit::find($businessUnitId);
            if (! $businessUnit || ! $businessUnit->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business unit not found or inactive',
                ], 404);
            }

            if (! $businessUnit->isKatoEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'KATO core banking is not enabled for this business unit',
                ], 422);
            }

            $agent = Agent::where('business_unit_id', $businessUnitId)->find($agentId);
            if (! $agent) {
                return response()->json([
                    'success' => false,
                    'message' => "Agent with id '{$agentId}' not found for business unit {$businessUnitId}",
                ], 422);
            }

            if ($agentError = $this->resolveKatoAgentAccountNumberOrFail($agent)) {
                return $agentError;
            }

            $customerAccount = $this->resolveCustomerAccount($businessUnitId, $accountNumber);
            if (! $customerAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client account not found for this business unit',
                ], 422);
            }

            $client = $customerAccount->accountable;
            if (! $client instanceof Client || ! $client->kato_member_no) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client is not registered with KATO yet',
                ], 422);
            }

            $terminal = $this->resolveTerminalOrFail($businessUnitId, $deviceSerial);
            $isAssignedToTerminal = $terminal->agents()->where('agents.id', $agent->id)->exists();
            if (! $isAssignedToTerminal) {
                return response()->json([
                    'success' => false,
                    'message' => "Agent '{$agent->id}' is not actively assigned to terminal '{$deviceSerial}'",
                ], 422);
            }

            $transactionType = $this->resolveTransactionTypeOrFail($businessUnitId, $transactionTypeCode);
            $calculation = $this->calculationService->calculateSplit(
                $transactionType->id,
                0,
                $businessUnitId
            );

            $katoPayload = $this->buildKatoEnquiryPayload($agent, $customerAccount, $calculation);
            $katoService = new KatoService();

            if ($operation === 'mini-statement') {
                $katoResult = $katoService->miniStatement($businessUnit, $katoPayload, $limit);
            } else {
                $katoResult = $katoService->balanceEnquiry($businessUnit, $katoPayload);
            }

            if (! $katoResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $katoResult['error'] ?? 'KATO enquiry failed',
                ], 422);
            }

            $katoData = $katoResult['data'] ?? [];
            $katoTransactionId = $katoData['commissionRef'] ?? null;
            $metadata = $request->input('metadata');
            $metadata = is_array($metadata) ? $metadata : [];
            $metadata['kato_api_response'] = $katoResult['full_response'] ?? null;
            $metadata['kato_payload'] = $katoPayload;

            // Keep requested account first so Android (which often reads balances[0])
            // shows the enquired account balance instead of another product (e.g. Savings=0).
            $orderedBalances = $this->prioritizeRequestedAccountBalance(
                is_array($katoData['balances'] ?? null) ? $katoData['balances'] : [],
                $customerAccount->account_number,
                $customerAccount->kato_account_code
            );
            $primaryBalance = isset($orderedBalances[0]['balance'])
                ? (float) $orderedBalances[0]['balance']
                : 0.0;

            $transactionId = null;
            DB::transaction(function () use (
                &$transactionId,
                $businessUnitId,
                $customerAccount,
                $agent,
                $terminal,
                $transactionType,
                $calculation,
                $reference,
                $metadata,
                $katoTransactionId,
                $operation,
                $primaryBalance
            ) {
                $transaction = Transaction::create([
                    'business_unit_id' => $businessUnitId,
                    'transaction_type_id' => $transactionType->id,
                    'agent_id' => $agent->id,
                    'terminal_id' => $terminal->id,
                    'client_id' => $customerAccount->accountable_id,
                    'account_number' => $customerAccount->account_number,
                    'transaction_amount' => $operation === 'balance-enquiry' ? $primaryBalance : 0,
                    'transaction_cost' => $calculation['transaction_cost'],
                    'commission' => $calculation['commission'],
                    'business_share' => $calculation['business_share'],
                    'transaction_date' => now(),
                    'status' => 'completed',
                    'reference' => $reference,
                    'metadata' => $metadata,
                    'created_by' => null,
                    'kato_transaction_id' => $katoTransactionId,
                    'kato_synced_at' => now(),
                ]);

                $transactionId = $transaction->id;
                $this->internalLedgerPostingService->postForNewTransaction($transaction);
            });

            if (! empty($katoData['balances'])) {
                $katoService->syncBalancesToLocalAccounts($client, $katoData['balances']);
            }

            $responseData = [
                'id' => $transactionId,
                'accountNumber' => $customerAccount->account_number,
                'memberNo' => (string) $client->kato_member_no,
            ];

            if ($operation === 'mini-statement') {
                $responseData['transactions'] = $katoData['transactions'] ?? [];
                $responseData['count'] = $katoData['count'] ?? count($responseData['transactions']);
            } else {
                $responseData['balances'] = $orderedBalances;
                $responseData['balance'] = $primaryBalance;
                $responseData['amount'] = $primaryBalance;
            }

            if (array_key_exists('enquiryCommission', $katoData)) {
                $responseData['enquiryCommission'] = $katoData['enquiryCommission'];
            }

            return response()->json([
                'success' => true,
                'message' => $operation === 'mini-statement'
                    ? 'Mini-statement retrieved successfully'
                    : 'Balance enquiry processed successfully',
                'data' => $responseData,
            ], 201);
        } catch (InternalLedgerPostingException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Handle new-style request with card, PIN, OTP, and code-based lookups
     */
    protected function handleNewStyleRequest(Request $request)
    {
        Log::info('Transaction API cardTransaction start', [
            'business_unit_id' => $request->input('business_unit_id'),
            'transaction_type_code' => $request->input('transaction_type_code'),
            'amount' => $request->input('amount'),
            'device_serial' => $request->input('device_serial'),
            'has_card_number' => $request->filled('card_number'),
            'has_otp' => $request->filled('otp'),
        ]);

        // Validate new-style request
        $validator = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'card_number' => 'required|string|max:255',
            'pin' => 'required|string|min:4|max:6',
            'otp' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'transaction_type_code' => 'required|string|max:50',
            'device_serial' => 'required|string|max:255',
            'client_id' => 'nullable|exists:clients,id',
            'reference' => 'nullable|string|max:255|unique:transactions,reference',
            'metadata' => 'nullable|array',
            'status' => 'nullable|in:pending,completed,failed,cancelled',
        ]);

        if ($validator->fails()) {
            Log::info('Transaction API cardTransaction validation_failed', [
                'errors' => $validator->errors()->toArray(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $businessUnitId = $request->input('business_unit_id');
        $cardNumber = $request->input('card_number');
        $pin = $request->input('pin');
        $otp = $request->input('otp');
        $amount = $request->input('amount');
        $transactionTypeCode = $request->input('transaction_type_code');
        $deviceSerial = $request->input('device_serial');

        // 1. Validate business unit
        $businessUnit = BusinessUnit::find($businessUnitId);
        if (! $businessUnit || ! $businessUnit->is_active) {
            Log::info('Transaction API cardTransaction business_unit_invalid', [
                'business_unit_id' => $businessUnitId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Business unit not found or inactive',
            ], 404);
        }

        // 2. Lookup and validate card
        $card = Card::where('card_number', $cardNumber)
            ->where('business_unit_id', $businessUnitId)
            ->first();

        if (! $card) {
            Log::info('Transaction API cardTransaction card_not_found', [
                'business_unit_id' => $businessUnitId,
                'card_number' => $cardNumber,
            ]);

            return response()->json([
                'success' => false,
                'message' => "Card with number '{$cardNumber}' not found for business unit {$businessUnitId}",
            ], 422);
        }

        // Validate card status
        if (! $card->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Card is not active',
            ], 422);
        }

        if ($card->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Card has expired',
            ], 422);
        }

        if ($card->isBlocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Card is blocked',
            ], 422);
        }

        // 3. Authenticate PIN
        if (! $card->verifyPin($pin)) {
            Log::info('Transaction API cardTransaction invalid_pin', [
                'card_number' => $cardNumber,
                'business_unit_id' => $businessUnitId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid PIN',
            ], 422);
        }

        // 4. Validate OTP (4–6 numeric digits)
        if (! preg_match('/^\d{4,6}$/', (string) $otp)) {
            Log::info('Transaction API cardTransaction invalid_otp', [
                'business_unit_id' => $businessUnitId,
                'card_number' => $cardNumber,
                'otp_length' => strlen((string) $otp),
                'otp_digits_only' => (bool) preg_match('/^\d+$/', (string) $otp),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP',
            ], 422);
        }

        // TODO: Implement OTP validation logic based on your OTP system
        // Examples:
        // - Check against OTP table with expiration time
        // - Validate TOTP using secret
        // - Call external OTP verification service

        // 5. Extract account number from card
        $account = $card->account;
        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => 'Card is not associated with an account',
            ], 422);
        }

        $accountNumber = $account->account_number;

        // 6. Resolve terminal and agent from device_serial
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

        // 7. Resolve transaction type from transaction_type_code
        $transactionType = TransactionType::where('code', $transactionTypeCode)
            ->where(function ($query) use ($businessUnitId) {
                // First try assigned global transaction types
                $query->where(function ($q) use ($businessUnitId) {
                    $q->whereNull('business_unit_id')
                      ->whereHas('businessUnits', function ($q2) use ($businessUnitId) {
                          $q2->where('business_units.id', $businessUnitId);
                      });
                })
                // Fall back to business-specific transaction types
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

        // 8. Calculate transaction cost and commission
        $calculation = $this->calculationService->calculateSplit(
            $transactionType->id,
            $amount,
            $businessUnitId
        );

        $isKato = false;
        $katoTransactionId = null;
        $katoApiResponse = null;
        $metadata = $request->input('metadata');
        $clientId = $request->input('client_id') ?? $account->accountable_id;
        $clientForKatoCheck = $account->accountable;

        Log::info('Transaction API cardTransaction kato_check', [
            'business_unit_id' => $businessUnitId,
            'is_kato_bu' => $businessUnit->isKatoEnabled(),
            'is_cash_withdrawal' => $this->isCashWithdrawalTransaction($transactionType),
            'transaction_type_code' => $transactionTypeCode,
            'client_kato_member_no' => $clientForKatoCheck?->kato_member_no ? 'present' : 'missing',
        ]);

        // KATO-first for card cash withdrawal when core banking is KATO (same rule as cash deposit).
        if ($this->isCashWithdrawalTransaction($transactionType)) {
            if ($businessUnit->isKatoEnabled()) {
                $client = $account->accountable;
                if (! $client || ! $client->kato_member_no) {
                    Log::info('Transaction API cardTransaction client_not_on_kato', [
                        'account_id' => $account->id,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Client is not registered with KATO yet',
                    ], 422);
                }

                if ($agentError = $this->resolveKatoAgentAccountNumberOrFail($agent)) {
                    return $agentError;
                }

                $katoPayload = $this->buildKatoWithdrawPayload(
                    $agent,
                    $account,
                    $amount,
                    $pin,
                    $calculation
                );
                $katoRequestLog = $katoPayload;
                $katoRequestLog['memberPin'] = '[REDACTED]';

                Log::info('Transaction API cardTransaction kato_calling', [
                    'business_unit_id' => $businessUnitId,
                    'transaction_type_code' => $transactionTypeCode,
                    'kato_request' => $katoRequestLog,
                    'grep_hint' => 'grep "KATO API" storage/logs/laravel.log',
                ]);

                $katoService = new KatoService();
                $katoResult = $katoService->withdraw($businessUnit, $katoPayload);

                if (! $katoResult['success']) {
                    Log::info('Transaction API cardTransaction kato_failed', [
                        'error' => $katoResult['error'] ?? 'unknown',
                        'http_status' => $katoResult['http_status'] ?? null,
                        'kato_full_response' => $katoResult['full_response'] ?? null,
                        'kato_request' => $katoRequestLog,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $katoResult['error'] ?? 'KATO withdrawal failed',
                    ], 422);
                }

                Log::info('Transaction API cardTransaction kato_success', [
                    'business_unit_id' => $businessUnitId,
                    'kato_transaction_id' => $katoResult['data']['transactionId'] ?? null,
                    'kato_request' => $katoRequestLog,
                ]);

                $isKato = true;
                $katoTransactionId = $katoResult['data']['transactionId'] ?? null;
                $katoApiResponse = $katoResult['full_response'] ?? null;

                if ($katoApiResponse !== null) {
                    $metadata = is_array($metadata) ? $metadata : [];
                    $metadata['kato_api_response'] = $katoApiResponse;
                    $metadata['kato_payload'] = $katoPayload;
                }

                $this->refreshLocalBalancesFromKato($businessUnit, $client);
            } else {
                $businessUnit->loadMissing('coreBankingSystem');
                Log::info('Transaction API cardTransaction kato_skipped', [
                    'business_unit_id' => $businessUnitId,
                    'transaction_type_code' => $transactionTypeCode,
                    'internal_ledger_flow' => $transactionType->internal_ledger_flow,
                    'cbs_code' => $businessUnit->coreBankingSystem?->code,
                    'has_kato_company_id' => (bool) $businessUnit->kato_company_id,
                    'reason' => 'not_kato_core_banking',
                ]);
            }
        }

        if (! $isKato) {
            $cbsOutcome = $this->attemptConfigurableCbsPost(
                $businessUnit,
                $transactionType,
                $request,
                $account,
                $account->accountable,
                $agent,
                (float) $amount,
                $request->input('reference')
            );
            if ($cbsOutcome instanceof JsonResponse) {
                return $cbsOutcome;
            }
            if (is_array($cbsOutcome) && ($cbsOutcome['synced'] ?? false)) {
                $metadata = $this->mergeCbsMetadata($metadata, $cbsOutcome['metadata'] ?? []);
            }
        }

        // 9. Create transaction
        $transactionId = null;
        try {
            DB::transaction(function () use (
                &$transactionId,
                $businessUnitId,
                $transactionType,
                $agent,
                $terminal,
                $clientId,
                $cardNumber,
                $accountNumber,
                $amount,
                $calculation,
                $request,
                $metadata,
                $isKato,
                $katoTransactionId
            ) {
                $transaction = Transaction::create([
                    'business_unit_id' => $businessUnitId,
                    'transaction_type_id' => $transactionType->id,
                    'agent_id' => $agent->id,
                    'terminal_id' => $terminal->id,
                    'client_id' => $clientId,
                    'card_number' => $cardNumber,
                    'account_number' => $accountNumber,
                    'transaction_amount' => $amount,
                    'transaction_cost' => $calculation['transaction_cost'],
                    'commission' => $calculation['commission'],
                    'business_share' => $calculation['business_share'],
                    'transaction_date' => $request->input('transaction_date') ?? now(),
                    'status' => $request->input('status', 'completed'),
                    'reference' => $request->input('reference'),
                    'metadata' => $metadata,
                    'created_by' => null,
                    'kato_transaction_id' => $isKato ? $katoTransactionId : null,
                    'kato_synced_at' => $isKato ? now() : null,
                ]);

                $transactionId = $transaction->id;
                $this->internalLedgerPostingService->postForNewTransaction($transaction);
            });
        } catch (InternalLedgerPostingException $e) {
            Log::error('Transaction API cardTransaction ledger_error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $transaction = Transaction::findOrFail($transactionId);

        Log::info('Transaction API cardTransaction success', [
            'transaction_id' => $transaction->id,
            'business_unit_id' => $businessUnitId,
            'card_number' => $cardNumber,
            'account_number' => $accountNumber,
            'amount' => $amount,
            'transaction_type_code' => $transactionTypeCode,
            'device_serial' => $deviceSerial,
            'kato' => $isKato,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transaction created successfully',
            'data' => [
                'id' => $transaction->id,
                'business_unit_id' => $transaction->business_unit_id,
                'card_number' => substr($cardNumber, -4), // Return only last 4 digits for security
                'account_number' => $accountNumber,
                'transaction_amount' => $transaction->transaction_amount,
                'transaction_cost' => $transaction->transaction_cost,
                'commission' => $transaction->commission,
                'business_share' => $transaction->business_share,
                'transaction_date' => $transaction->transaction_date->format('Y-m-d H:i:s'),
                'status' => $transaction->status,
                'reference' => $transaction->reference,
                'transaction_type_code' => $transactionTypeCode,
                'device_serial' => $deviceSerial,
                'agent_id' => $agent->id,
                'terminal_id' => $terminal->id,
            ],
        ], 201);
    }

    /**
     * Handle old-style request for backward compatibility
     */
    protected function handleOldStyleRequest(Request $request)
    {
        $businessUnitId = $request->input('business_unit_id');

        if (! $businessUnitId) {
            return response()->json([
                'success' => false,
                'message' => 'Business unit ID is required',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'transaction_type_id' => 'required|exists:transaction_types,id',
            'transaction_amount' => 'required|numeric|min:0.01',
            'agent_id' => 'nullable|exists:agents,id',
            'terminal_id' => 'nullable|exists:terminals,id',
            'client_id' => 'nullable|exists:clients,id',
            'transaction_date' => 'nullable|date',
            'reference' => 'nullable|string|max:255|unique:transactions,reference',
            'metadata' => 'nullable|array',
            'status' => 'nullable|in:pending,completed,failed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify agent/terminal belongs to business unit
        if ($request->filled('agent_id')) {
            $agent = Agent::where('business_unit_id', $businessUnitId)
                ->findOrFail($request->agent_id);
        }

        if ($request->filled('terminal_id')) {
            $terminal = Terminal::where('business_unit_id', $businessUnitId)
                ->findOrFail($request->terminal_id);
        }

        // Calculate transaction cost and commission
        $calculation = $this->calculationService->calculateSplit(
            $request->transaction_type_id,
            $request->transaction_amount,
            $businessUnitId
        );

        // Create transaction
        $transaction = Transaction::create([
            'business_unit_id' => $businessUnitId,
            'transaction_type_id' => $request->transaction_type_id,
            'agent_id' => $request->agent_id,
            'terminal_id' => $request->terminal_id,
            'client_id' => $request->client_id,
            'transaction_amount' => $request->transaction_amount,
            'transaction_cost' => $calculation['transaction_cost'],
            'commission' => $calculation['commission'],
            'business_share' => $calculation['business_share'],
            'transaction_date' => $request->transaction_date ?? now(),
            'status' => $request->status ?? 'completed',
            'reference' => $request->reference,
            'metadata' => $request->metadata,
            'created_by' => null, // API created
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transaction created successfully',
            'data' => [
                'id' => $transaction->id,
                'transaction_amount' => $transaction->transaction_amount,
                'transaction_cost' => $transaction->transaction_cost,
                'commission' => $transaction->commission,
                'business_share' => $transaction->business_share,
                'transaction_date' => $transaction->transaction_date->format('Y-m-d H:i:s'),
                'status' => $transaction->status,
                'reference' => $transaction->reference,
            ],
        ], 201);
    }

    /**
     * Put the enquired account first in the KATO balances list.
     *
     * @param  array<int, array<string, mixed>>  $balances
     * @return array<int, array<string, mixed>>
     */
    protected function prioritizeRequestedAccountBalance(
        array $balances,
        string $accountNumber,
        ?string $accountCode = null
    ): array {
        if ($balances === []) {
            return [];
        }

        $matchIndex = null;
        foreach ($balances as $index => $row) {
            $rowAccountNumber = isset($row['accountNumber']) ? (string) $row['accountNumber'] : '';
            $rowAccountCode = isset($row['accountCode']) ? (string) $row['accountCode'] : '';

            if ($rowAccountNumber !== '' && $rowAccountNumber === $accountNumber) {
                $matchIndex = $index;
                break;
            }

            if ($accountCode && $rowAccountCode !== '' && $rowAccountCode === (string) $accountCode) {
                $matchIndex = $index;
                break;
            }
        }

        if ($matchIndex === null || $matchIndex === 0) {
            return array_values($balances);
        }

        $matched = $balances[$matchIndex];
        unset($balances[$matchIndex]);

        return array_values(array_merge([$matched], $balances));
    }
}
