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
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Validate request
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
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
}
