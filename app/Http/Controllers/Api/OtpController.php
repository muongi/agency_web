<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Otp;
use App\Models\Card;
use App\Models\Account;
use App\Models\Client;
use App\Models\BusinessUnit;
use App\Services\SmsService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class OtpController extends Controller
{
    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Send OTP to client's phone number.
     * 
     * Accepts either card_number or account_number to lookup the account
     * and send OTP to the client's registered phone number.
     */
    public function sendOtp(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'business_unit_id' => 'required|integer|exists:business_units,id',
                'card_number' => 'nullable|string|max:255',
                'account_number' => 'nullable|string|max:255',
                'purpose' => 'nullable|string|max:50',
            ]);

            // Ensure at least one of card_number or account_number is provided
            if (!$request->has('card_number') && !$request->has('account_number')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Either card_number or account_number is required',
                ], 422);
            }

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $businessUnitId = $request->input('business_unit_id');
            $cardNumber = $request->input('card_number');
            $accountNumber = $request->input('account_number');
            $purpose = $request->input('purpose', 'transaction');

            // Validate business unit
            $businessUnit = BusinessUnit::find($businessUnitId);
            if (!$businessUnit || !$businessUnit->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business unit not found or inactive',
                ], 404);
            }

            // Lookup account
            $account = null;
            $card = null;

            if ($cardNumber) {
                // Lookup by card number
                $card = Card::where('card_number', $cardNumber)
                    ->where('business_unit_id', $businessUnitId)
                    ->first();

                if (!$card) {
                    return response()->json([
                        'success' => false,
                        'message' => "Card with number '{$cardNumber}' not found for business unit {$businessUnitId}",
                    ], 422);
                }

                $account = $card->account;
                if (!$account) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Card is not associated with an account',
                    ], 422);
                }
            } elseif ($accountNumber) {
                // Lookup by account number
                $account = Account::where('account_number', $accountNumber)
                    ->where('business_unit_id', $businessUnitId)
                    ->first();

                if (!$account) {
                    return response()->json([
                        'success' => false,
                        'message' => "Account with number '{$accountNumber}' not found for business unit {$businessUnitId}",
                    ], 422);
                }
            }

            // Verify account belongs to business unit
            if ($account->business_unit_id != $businessUnitId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account does not belong to the specified business unit',
                ], 422);
            }

            // Get client from account (accountable must be Client)
            if ($account->accountable_type !== Client::class) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is not associated with a client. OTP can only be sent to client accounts.',
                ], 422);
            }

            $client = $account->accountable;
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found for this account',
                ], 422);
            }

            // Get client phone number
            $phoneNumber = $client->phone_number;
            if (!$phoneNumber) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client phone number is not registered',
                ], 422);
            }

            // Generate 5-digit OTP
            $otpCode = Otp::generateCode();
            $expiresAt = now()->addMinutes(10); // 10 minutes expiration

            // Create OTP record
            $otp = Otp::create([
                'business_unit_id' => $businessUnitId,
                'account_id' => $account->id,
                'card_number' => $cardNumber,
                'account_number' => $accountNumber ?? $account->account_number,
                'phone_number' => $phoneNumber,
                'otp_code' => $otpCode,
                'expires_at' => $expiresAt,
                'purpose' => $purpose,
                'metadata' => [
                    'client_id' => $client->id,
                    'client_name' => $client->full_name,
                ],
            ]);

            // Prepare SMS message
            $message = "Your OTP code is {$otpCode}. Valid for 10 minutes. Do not share this code.";

            // Send SMS
            $smsResult = $this->smsService->sendSms(
                $businessUnitId,
                $phoneNumber,
                $message,
                $client->id,
                "OTP-{$otp->id}"
            );

            if (!$smsResult['success']) {
                // Log error but don't fail the request - OTP is still created
                Log::error('OTP SMS sending failed', [
                    'otp_id' => $otp->id,
                    'phone_number' => substr($phoneNumber, -4), // Log only last 4 digits
                    'error' => $smsResult['message'],
                    'business_unit_id' => $businessUnitId,
                ]);

                // Still return success but note SMS issue
                return response()->json([
                    'success' => true,
                    'message' => 'OTP generated successfully, but SMS sending failed. Please contact support.',
                    'data' => [
                        'otp_id' => $otp->id,
                        'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                        'expires_in_minutes' => 10,
                        'sms_status' => 'failed',
                        'sms_error' => $smsResult['message'],
                    ],
                ], 201);
            }

            Log::info('OTP sent successfully', [
                'otp_id' => $otp->id,
                'phone_number' => substr($phoneNumber, -4), // Log only last 4 digits
                'business_unit_id' => $businessUnitId,
                'client_id' => $client->id,
                'purpose' => $purpose,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully to registered phone number',
                'data' => [
                    'otp_id' => $otp->id,
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                    'expires_in_minutes' => 10,
                    'sms_status' => 'sent',
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('OTP Generation Failed: ' . $e->getMessage(), [
                'request' => $request->except(['card_number', 'account_number']), // Don't log sensitive data
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate OTP',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Forward OTP to client's phone number.
     * 
     * Accepts an OTP code in the request payload and forwards it via SMS
     * to the client's registered phone number. This differs from sendOtp
     * which generates the OTP automatically.
     * 
     * Accepts either card_number or account_number to lookup the account.
     */
    public function forwardOtp(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'business_unit_id' => 'required|integer|exists:business_units,id',
                'otp_code' => ['required', 'string', 'regex:/^\d{5}$/'],
                'card_number' => 'nullable|string|max:255',
                'account_number' => 'nullable|string|max:255',
                'purpose' => 'nullable|string|max:50',
            ]);

            // Ensure at least one of card_number or account_number is provided
            if (!$request->has('card_number') && !$request->has('account_number')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Either card_number or account_number is required',
                ], 422);
            }

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $businessUnitId = $request->input('business_unit_id');
            $otpCode = $request->input('otp_code');
            $cardNumber = $request->input('card_number');
            $accountNumber = $request->input('account_number');
            $purpose = $request->input('purpose', 'transaction');

            // Validate business unit
            $businessUnit = BusinessUnit::find($businessUnitId);
            if (!$businessUnit || !$businessUnit->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business unit not found or inactive',
                ], 404);
            }

            // Lookup account
            $account = null;
            $card = null;

            if ($cardNumber) {
                // Lookup by card number
                $card = Card::where('card_number', $cardNumber)
                    ->where('business_unit_id', $businessUnitId)
                    ->first();

                if (!$card) {
                    return response()->json([
                        'success' => false,
                        'message' => "Card with number '{$cardNumber}' not found for business unit {$businessUnitId}",
                    ], 422);
                }

                $account = $card->account;
                if (!$account) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Card is not associated with an account',
                    ], 422);
                }
            } elseif ($accountNumber) {
                // Lookup by account number
                $account = Account::where('account_number', $accountNumber)
                    ->where('business_unit_id', $businessUnitId)
                    ->first();

                if (!$account) {
                    return response()->json([
                        'success' => false,
                        'message' => "Account with number '{$accountNumber}' not found for business unit {$businessUnitId}",
                    ], 422);
                }
            }

            // Verify account belongs to business unit
            if ($account->business_unit_id != $businessUnitId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account does not belong to the specified business unit',
                ], 422);
            }

            // Get client from account (accountable must be Client)
            if ($account->accountable_type !== Client::class) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is not associated with a client. OTP can only be sent to client accounts.',
                ], 422);
            }

            $client = $account->accountable;
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found for this account',
                ], 422);
            }

            // Get client phone number
            $phoneNumber = $client->phone_number;
            if (!$phoneNumber) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client phone number is not registered',
                ], 422);
            }

            // Use provided OTP code (already validated as 5 digits)
            $expiresAt = now()->addMinutes(10); // 10 minutes expiration

            // Create OTP record with provided code
            $otp = Otp::create([
                'business_unit_id' => $businessUnitId,
                'account_id' => $account->id,
                'card_number' => $cardNumber,
                'account_number' => $accountNumber ?? $account->account_number,
                'phone_number' => $phoneNumber,
                'otp_code' => $otpCode,
                'expires_at' => $expiresAt,
                'purpose' => $purpose,
                'metadata' => [
                    'client_id' => $client->id,
                    'client_name' => $client->full_name,
                ],
            ]);

            // Prepare SMS message
            $message = "Your OTP code is {$otpCode}. Valid for 10 minutes. Do not share this code.";

            // Send SMS
            $smsResult = $this->smsService->sendSms(
                $businessUnitId,
                $phoneNumber,
                $message,
                $client->id,
                "OTP-{$otp->id}"
            );

            if (!$smsResult['success']) {
                // Log error but don't fail the request - OTP is still created
                Log::error('OTP SMS forwarding failed', [
                    'otp_id' => $otp->id,
                    'phone_number' => substr($phoneNumber, -4), // Log only last 4 digits
                    'error' => $smsResult['message'],
                    'business_unit_id' => $businessUnitId,
                ]);

                // Still return success but note SMS issue
                return response()->json([
                    'success' => true,
                    'message' => 'OTP stored successfully, but SMS sending failed. Please contact support.',
                    'data' => [
                        'otp_id' => $otp->id,
                        'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                        'expires_in_minutes' => 10,
                        'sms_status' => 'failed',
                        'sms_error' => $smsResult['message'],
                    ],
                ], 201);
            }

            Log::info('OTP forwarded successfully', [
                'otp_id' => $otp->id,
                'phone_number' => substr($phoneNumber, -4), // Log only last 4 digits
                'business_unit_id' => $businessUnitId,
                'client_id' => $client->id,
                'purpose' => $purpose,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'OTP forwarded successfully to registered phone number',
                'data' => [
                    'otp_id' => $otp->id,
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                    'expires_in_minutes' => 10,
                    'sms_status' => 'sent',
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('OTP Forwarding Failed: ' . $e->getMessage(), [
                'request' => $request->except(['card_number', 'account_number', 'otp_code']), // Don't log sensitive data
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to forward OTP',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
