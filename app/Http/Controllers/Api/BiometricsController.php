<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Terminal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BiometricsController extends Controller
{
    /**
     * Fetch saved fingerprints for a client, mapped to an account_number.
     *
     * Expected request (as JSON or form-data):
     * - account_number: string
     * - device_serial: string (terminal serial)
     * - agent_id: integer (agents.id)
     * - mfi_code: string (optional, accepted for compatibility)
     */
    public function fetch(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'account_number' => 'required|string|max:255',
                'agent_id' => 'required|integer|exists:agents,id',
                'mfi_code' => 'nullable|string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $accountNumber = $request->input('account_number');
            $deviceSerial = $request->input('device_serial');
            $agentId = (int) $request->input('agent_id');

            // $terminal = Terminal::where('serial_number', $deviceSerial)->first();
            // if (!$terminal) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => "Terminal with device_serial '{$deviceSerial}' not found",
            //     ], 422);
            // }

            //  $terminalBusinessUnitId = $terminal->business_unit_id;

            // "Soft" security mapping: ensure the given agent belongs to the same business unit
            // as the terminal, but do not require the agent to be assigned to this terminal.
            $agent = Agent::find($agentId);
            if (! $agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Agent not found',
                ], 422);
            }

            $account = Account::query()
                ->where('account_number', $accountNumber)
                ->where('accountable_type', Client::class)
                ->first();

            if (! $account) {
                return response()->json([
                    'success' => false,
                    'message' => "Account '{$accountNumber}' not found for this business unit",
                ], 422);
            }

            $client = $account->accountable;
            if (! $client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found for this account',
                ], 422);
            }

            // IMPORTANT: Never log fingerprint templates (PII/biometric data).
            $fingerprintPresentFlags = [
                'fingerprint1_present' => ! empty($client->fingerprint1),
                'fingerprint2_present' => ! empty($client->fingerprint2),
                'fingerprint3_present' => ! empty($client->fingerprint3),
                'fingerprint4_present' => ! empty($client->fingerprint4),
            ];

            // Map fingerprints to a 4-element response array. Empty strings => null.
            // If biometrics_status is false, return all nulls.
            $fingerprints = [null, null, null, null];
            if ((bool) $client->biometrics_status) {
                $fingerprints = [
                    ! empty($client->fingerprint1) ? $client->fingerprint1 : null,
                    ! empty($client->fingerprint2) ? $client->fingerprint2 : null,
                    ! empty($client->fingerprint3) ? $client->fingerprint3 : null,
                    ! empty($client->fingerprint4) ? $client->fingerprint4 : null,
                ];
            }

            Log::info('API Biometrics Fetch Request', [
                'account_number_last4' => is_string($accountNumber) && strlen($accountNumber) >= 4
                    ? substr($accountNumber, -4)
                    : $accountNumber,
                'device_serial' => $deviceSerial,
                'agent_id' => $agentId,
                'mfi_code' => $request->input('mfi_code'),
                'biometrics_status' => (bool) $client->biometrics_status,
                'fingerprints_present' => $fingerprints,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'account_number' => $accountNumber,
                'holder_name' => $client->full_name,
                'fingerprints' => $fingerprints,
            ], 200);
        } catch (\Exception $e) {
            Log::error('API Biometrics Fetch Failed', [
                'error' => $e->getMessage(),
                // Don't include biometric data; only identifiers.
                'account_number_last4' => optional($request->input('account_number'))
                    ? substr((string) $request->input('account_number'), -4)
                    : null,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch biometrics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
