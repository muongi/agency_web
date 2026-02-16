<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Card;
use App\Models\Account;
use App\Models\Agent;
use App\Models\Terminal;
use App\Models\TransactionType;
use App\Models\BusinessUnit;
use App\Services\TransactionCalculationService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ApiTransactionController extends Controller
{
    protected $calculationService;

    public function __construct(TransactionCalculationService $calculationService)
    {
        $this->calculationService = $calculationService;
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
        try {
            // Determine if new-style or old-style request
            $isNewStyle = $request->has('card_number') && $request->has('transaction_type_code') && $request->has('device_serial');

            if ($isNewStyle) {
                return $this->handleNewStyleRequest($request);
            } else {
                return $this->handleOldStyleRequest($request);
            }
        } catch (\Exception $e) {
            Log::error('API Transaction Creation Failed: ' . $e->getMessage(), [
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

    /**
     * Handle new-style request with card, PIN, OTP, and code-based lookups
     */
    protected function handleNewStyleRequest(Request $request)
    {
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
        if (!$businessUnit || !$businessUnit->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Business unit not found or inactive',
            ], 404);
        }

        // 2. Lookup and validate card
        $card = Card::where('card_number', $cardNumber)
            ->where('business_unit_id', $businessUnitId)
            ->first();

        if (!$card) {
            return response()->json([
                'success' => false,
                'message' => "Card with number '{$cardNumber}' not found for business unit {$businessUnitId}",
            ], 422);
        }

        // Validate card status
        if (!$card->isActive()) {
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
        if (!$card->verifyPin($pin)) {
            Log::warning('API Transaction Failed - Invalid PIN', [
                'card_number' => substr($cardNumber, -4), // Log only last 4 digits
                'business_unit_id' => $businessUnitId,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid PIN',
            ], 422);
        }

        // 4. Validate OTP (basic validation - can be enhanced based on your OTP system)
        if (empty($otp) || strlen($otp) < 4) {
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
        if (!$account) {
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

        if (!$terminal) {
            return response()->json([
                'success' => false,
                'message' => "Terminal with device_serial '{$deviceSerial}' not found for business unit {$businessUnitId}",
            ], 422);
        }

        $agent = $terminal->agents()->first();
        if (!$agent) {
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

        if (!$transactionType) {
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

        // 9. Create transaction
        $transaction = Transaction::create([
            'business_unit_id' => $businessUnitId,
            'transaction_type_id' => $transactionType->id,
            'agent_id' => $agent->id,
            'terminal_id' => $terminal->id,
            'client_id' => $request->input('client_id'),
            'card_number' => $cardNumber,
            'account_number' => $accountNumber,
            'transaction_amount' => $amount,
            'transaction_cost' => $calculation['transaction_cost'],
            'commission' => $calculation['commission'],
            'business_share' => $calculation['business_share'],
            'transaction_date' => $request->input('transaction_date') ?? now(),
            'status' => $request->input('status', 'completed'),
            'reference' => $request->input('reference'),
            'metadata' => $request->input('metadata'),
            'created_by' => null, // API created
        ]);

        Log::info('API Transaction Created Successfully', [
            'transaction_id' => $transaction->id,
            'business_unit_id' => $businessUnitId,
            'card_number' => substr($cardNumber, -4), // Log only last 4 digits
            'account_number' => $accountNumber,
            'amount' => $amount,
            'transaction_type_code' => $transactionTypeCode,
            'device_serial' => $deviceSerial,
            'ip' => $request->ip(),
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

        if (!$businessUnitId) {
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
}
