<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Agent;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AgentAuthController extends Controller
{
    /**
     * Authenticate agent and return profile with accounts.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Log incoming request
        Log::info('API Agent Login Request', [
            'username' => $request->username,
            'device_serial' => $request->input('device_serial'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Validate request
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
            'device_serial' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            Log::warning('API Agent Login Validation Failed', [
                'username' => $request->username,
                'errors' => $validator->errors()->toArray(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Find agent by username (soft-deleted agents are automatically excluded)
        $agent = Agent::where('username', $request->username)->first();

        if (!$agent) {
            Log::warning('API Agent Login Failed - Agent Not Found', [
                'username' => $request->username,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid username or password',
            ], 401);
        }

        // Verify password
        if (!Hash::check($request->password, $agent->password)) {
            Log::warning('API Agent Login Failed - Invalid Password', [
                'username' => $request->username,
                'agent_id' => $agent->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid username or password',
            ], 401);
        }

        // Load relationships
        $agent->load(['businessUnit', 'accounts' => function ($query) {
            $query->whereIn('account_type', ['float', 'commission'])
                  ->where('status', 'active');
        }]);

        // Build response data
        $responseData = [
            'agent' => [
                'id' => $agent->id,
                'first_name' => $agent->first_name,
                'second_name' => $agent->second_name,
                'full_name' => $agent->full_name,
                'email' => $agent->email,
                'phone_number' => $agent->phone_number,
                'photo' => $agent->photo,
                'username' => $agent->username,
                'can_write_cards' => (bool) $agent->can_write_cards,
                'can_rewrite_cards' => (bool) $agent->can_rewrite_cards,
                'pin_set' => $agent->hasCollectionPin(),
                'pin_must_change' => $agent->hasCollectionPin() && (bool) $agent->pin_must_change,
                'pin_locked' => $agent->isPinLocked(),
                'collector' => app(\App\Services\CollectorCapService::class)->status($agent->id),
                'business_unit' => [
                    'id' => $agent->businessUnit->id,
                    'name' => $agent->businessUnit->name,
                ],
            ],
            'accounts' => $agent->accounts->map(function ($account) {
                return [
                    'id' => $account->id,
                    'account_number' => $account->account_number,
                    'account_type' => $account->account_type,
                    'balance' => number_format($account->balance, 2, '.', ''),
                    'currency' => $account->currency,
                    'status' => $account->status,
                ];
            })->values(),
        ];

        // Log successful login
        Log::info('API Agent Login Success', [
            'agent_id' => $agent->id,
            'username' => $agent->username,
            'device_serial' => $request->input('device_serial'),
            'business_unit_id' => $agent->businessUnit->id,
            'business_unit_name' => $agent->businessUnit->name,
            'accounts_count' => $agent->accounts->count(),
            'ip' => $request->ip(),
        ]);

        // Build full response
        $fullResponse = [
            'success' => true,
            'data' => $responseData,
        ];

        // Log full response sent to Android app
        Log::info('API Agent Login Response - Full Response', [
            'response_status' => 200,
            'full_response' => $fullResponse,
        ]);

        return response()->json($fullResponse, 200);
    }

    /**
     * Agent changes their own collection PIN on the TPE: POST /api/v1/agent/pin
     * {agent_id, old_pin, new_pin}. Wrong old PIN counts toward the lockout.
     */
    public function changePin(Request $request)
    {
        $v = Validator::make($request->all(), [
            'agent_id' => 'required|integer',
            'old_pin' => 'required|string',
            'new_pin' => ['required', 'string', 'regex:/^\d{4}$/', 'different:old_pin'],
        ]);
        if ($v->fails()) {
            return response()->json(['success' => false, 'code' => 'VALIDATION', 'message' => 'Validation failed', 'errors' => $v->errors()], 422);
        }

        $agent = Agent::find((int) $request->input('agent_id'));
        if (! $agent || ! $agent->hasCollectionPin()) {
            return response()->json(['success' => false, 'code' => 'PIN_NOT_SET', 'message' => 'No PIN set for this agent'], 404);
        }

        $newPin = (string) $request->input('new_pin');
        if (self::isTrivialPin($newPin) || $newPin === (string) config('sunu.default_agent_pin')) {
            return response()->json(['success' => false, 'code' => 'PIN_TOO_SIMPLE', 'message' => 'PIN too simple'], 422);
        }

        $result = $agent->checkCollectionPin((string) $request->input('old_pin'));
        if ($result === 'locked') {
            Log::warning('SUNU_PIN_LOCKED', ['agent_id' => $agent->id, 'via' => 'change_pin']);

            return response()->json(['success' => false, 'code' => 'PIN_LOCKED', 'message' => 'PIN locked. Ask your supervisor to reset it.'], 423);
        }
        if ($result !== 'ok') {
            Log::warning('SUNU_PIN_FAILED', ['agent_id' => $agent->id, 'via' => 'change_pin', 'attempts' => $agent->pin_failed_attempts]);

            return response()->json(['success' => false, 'code' => 'INVALID_PIN', 'message' => 'Invalid PIN', 'attempts_left' => $agent->pinAttemptsLeft()], 401);
        }

        $agent->setCollectionPin($newPin, false);
        Log::info('SUNU_PIN_CHANGED', ['agent_id' => $agent->id]);

        return response()->json(['success' => true, 'message' => 'PIN changed']);
    }

    /** 0000, 1111 … and straight runs like 1234 / 4321. */
    public static function isTrivialPin(string $pin): bool
    {
        if (preg_match('/^(\d)\1{3}$/', $pin)) {
            return true;
        }

        return str_contains('01234567890', $pin) || str_contains('09876543210', $pin);
    }
}
