<?php

namespace App\Services;

use App\Models\AccountSms;
use App\Models\SmsConfiguration;
use App\Models\SmsMessage;
use App\Models\BusinessUnit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send SMS using Orange SMS API
     */
    public function sendSms($businessUnitId, $recipient, $message, $clientId = null, $reference = null)
    {
        // Get business unit and check sender number
        $businessUnit = BusinessUnit::find($businessUnitId);
        if (!$businessUnit) {
            Log::info('SMS API send aborted', [
                'business_unit_id' => $businessUnitId,
                'reason' => 'business_unit_not_found',
            ]);

            return [
                'success' => false,
                'message' => 'Business unit not found'
            ];
        }

        if (!$businessUnit->sms_sender_number) {
            Log::info('SMS API send aborted', [
                'business_unit_id' => $businessUnitId,
                'reason' => 'sms_sender_number_not_configured',
            ]);

            return [
                'success' => false,
                'message' => 'SMS sender number not configured for this business unit'
            ];
        }

        // Check SMS balance first
        if (!AccountSms::checkBalance($businessUnitId)) {
            Log::info('SMS API send aborted', [
                'business_unit_id' => $businessUnitId,
                'reason' => 'insufficient_sms_balance',
            ]);

            return [
                'success' => false,
                'message' => 'Insufficient SMS balance'
            ];
        }

        // Get SMS configuration
        $config = SmsConfiguration::getActive();
        if (!$config || !SmsConfiguration::isConfigured()) {
            Log::info('SMS API send aborted', [
                'business_unit_id' => $businessUnitId,
                'reason' => 'sms_configuration_missing',
            ]);

            return [
                'success' => false,
                'message' => 'SMS configuration not found'
            ];
        }

        $credentials = $config->getCredentials();

        // Get access token
        $token = $this->getAccessToken($credentials);
        
        if (!$token) {
            Log::info('SMS API send aborted', [
                'business_unit_id' => $businessUnitId,
                'reason' => 'orange_token_failed',
            ]);

            return [
                'success' => false,
                'message' => 'Failed to authenticate with SMS provider'
            ];
        }

        // Extract sender number (remove tel: prefix if present)
        $senderNumber = preg_replace('/^tel:\+?/', '', $businessUnit->sms_sender_number);
        $senderNumber = preg_replace('/^\+/', '', $senderNumber); // Remove leading +

        // Ensure recipient has proper format (add tel: prefix)
        $recipientFormatted = preg_match('/^tel:/', $recipient) ? $recipient : 'tel:+' . preg_replace('/^\+/', '', $recipient);

        // Prepare SMS data for Orange API
        $smsData = [
            'outboundSMSMessageRequest' => [
                'address' => $recipientFormatted,
                'senderAddress' => 'tel:+' . $senderNumber,
                'outboundSMSTextMessage' => [
                    'message' => $message
                ]
            ]
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        // Create SMS message record with pending status
        $smsMessage = SmsMessage::create([
            'business_unit_id' => $businessUnitId,
            'sender_number' => $businessUnit->sms_sender_number,
            'recipient_number' => $recipient,
            'recipient_client_id' => $clientId,
            'message' => $message,
            'status' => 'pending',
            'sent_by_user_id' => auth()->id(),
        ]);

        $orangeUrl = 'https://api.orange.com/smsmessaging/v1/outbound/tel:+'.urlencode($senderNumber).'/requests';

        $logRequestBody = $this->sanitizeSmsDataForLog($smsData);

        try {
            Log::info('SMS API outgoing request', [
                'business_unit_id' => $businessUnitId,
                'url' => $orangeUrl,
                'authorization' => 'Bearer '.$this->maskBearerToken($token),
                'recipient_last4' => substr(preg_replace('/\D/', '', $recipient), -4),
                'sender' => $businessUnit->sms_sender_number,
                'reference' => $reference,
                'message_length' => strlen($message),
                'request_body' => $logRequestBody,
            ]);

            // Send SMS using Orange API v1 endpoint
            $response = Http::withHeaders($headers)
                ->post($orangeUrl, $smsData);

            $responseData = $response->json();

            Log::info('SMS API response', [
                'business_unit_id' => $businessUnitId,
                'status' => $response->status(),
                'successful' => $response->successful(),
                'payload' => $responseData ?? $response->body(),
            ]);
            
            // 201 Created is success for Orange API
            if ($response->status() === 201 || $response->successful()) {
                $messageId = $responseData['outboundSMSMessageRequest']['resourceURL'] ?? 
                            $responseData['outboundSMSMessageRequest']['id'] ?? 
                            null;

                // Update SMS message record
                $smsMessage->update([
                    'status' => 'sent',
                    'api_response' => $responseData,
                    'api_message_id' => $messageId,
                    'sent_at' => now(),
                ]);

                Log::info("SMS sent successfully. Message ID: {$messageId} for business unit: {$businessUnitId}");
                
                // Debit SMS credits (1 credit per SMS)
                AccountSms::insertAccountSms(
                    $businessUnitId,
                    1, // debit 1 SMS credit
                    0, // no credit
                    "SMS sent to {$recipient}",
                    auth()->id(),
                    $messageId,
                    [
                        'recipient' => $recipient,
                        'message' => $message,
                        'reference' => $reference
                    ]
                );

                return [
                    'success' => true,
                    'message' => 'SMS sent successfully',
                    'message_id' => $messageId,
                    'sms_message_id' => $smsMessage->id
                ];
            } else {
                $errorMessage = $responseData['error_description'] ?? $responseData['message'] ?? $response->body();
                
                // Update SMS message record with error
                $smsMessage->update([
                    'status' => 'failed',
                    'api_response' => $responseData,
                    'error_message' => $errorMessage,
                    'sent_at' => now(),
                ]);

                Log::info('SMS API send failed', [
                    'business_unit_id' => $businessUnitId,
                    'status' => $response->status(),
                    'error' => $errorMessage,
                    'sms_message_id' => $smsMessage->id,
                    'request_body' => $logRequestBody,
                    'response_payload' => $responseData ?? $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to send SMS: ' . $errorMessage,
                    'sms_message_id' => $smsMessage->id
                ];
            }
        } catch (\Exception $e) {
            // Update SMS message record with exception
            $smsMessage->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'sent_at' => now(),
            ]);

            Log::info('SMS API send exception', [
                'business_unit_id' => $businessUnitId,
                'message' => $e->getMessage(),
                'sms_message_id' => $smsMessage->id,
                'request_body' => $logRequestBody,
            ]);
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
                'sms_message_id' => $smsMessage->id
            ];
        }
    }

    /**
     * Send bulk SMS to multiple recipients
     */
    public function sendBulkSms($businessUnitId, $recipients, $message)
    {
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($recipients as $recipient) {
            $clientId = is_array($recipient) ? $recipient['client_id'] : null;
            $recipientNumber = is_array($recipient) ? $recipient['phone_number'] : $recipient;

            $result = $this->sendSms($businessUnitId, $recipientNumber, $message, $clientId);
            
            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }

            $results[] = [
                'recipient' => $recipientNumber,
                'client_id' => $clientId,
                'result' => $result
            ];

            // Add small delay to avoid rate limiting
            usleep(100000); // 100ms delay
        }

        return [
            'success' => $failureCount === 0,
            'total' => count($recipients),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'results' => $results
        ];
    }

    /**
     * @param  array<string, mixed>  $smsData
     * @return array<string, mixed>
     */
    private function sanitizeSmsDataForLog(array $smsData): array
    {
        $sanitized = json_decode(json_encode($smsData), true);
        if (! is_array($sanitized)) {
            return $smsData;
        }

        if (! config('logging.log_otp_plaintext')
            && isset($sanitized['outboundSMSMessageRequest']['outboundSMSTextMessage']['message'])) {
            $sanitized['outboundSMSMessageRequest']['outboundSMSTextMessage']['message'] = '[REDACTED]';
        }

        return $sanitized;
    }

    private function maskBearerToken(string $token): string
    {
        if (strlen($token) < 8) {
            return '***';
        }

        return '***'.substr($token, -4);
    }

    /**
     * Get access token from Orange API using Basic authentication
     */
    private function getAccessToken($credentials)
    {
        // If JWT token is provided and still valid, use it
        if (!empty($credentials['jwt_token'])) {
            Log::info('SMS API oauth request', [
                'method' => 'use_stored_jwt',
            ]);

            return $credentials['jwt_token'];
        }

        // Get token using Basic authentication with client credentials
        try {
            Log::info('SMS API oauth request', [
                'url' => 'https://api.orange.com/oauth/v3/token',
                'grant_type' => 'client_credentials',
            ]);

            $authString = base64_encode($credentials['client_id'] . ':' . $credentials['client_secret']);
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $authString,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post('https://api.orange.com/oauth/v3/token', [
                'grant_type' => 'client_credentials',
            ]);

            if ($response->successful()) {
                Log::info('SMS API oauth response', [
                    'status' => $response->status(),
                    'successful' => true,
                ]);
                $data = $response->json();

                return $data['access_token'] ?? null;
            }

            Log::info('SMS API oauth response', [
                'status' => $response->status(),
                'successful' => false,
                'payload' => $response->json() ?? $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::info('SMS API oauth exception', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get SMS balance for a business unit
     */
    public function getBalance($businessUnitId)
    {
        return AccountSms::getBalance($businessUnitId);
    }

    /**
     * Check if business unit has sufficient SMS balance
     */
    public function checkBalance($businessUnitId)
    {
        return AccountSms::checkBalance($businessUnitId);
    }

    /**
     * Check SMS delivery status (optional)
     */
    public function checkDeliveryStatus($messageId)
    {
        $config = SmsConfiguration::getActive();
        if (!$config) {
            return false;
        }

        $credentials = $config->getCredentials();
        $token = $this->getAccessToken($credentials);

        if (!$token) {
            return false;
        }

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ];

        try {
            $response = Http::withHeaders($headers)
                ->get($credentials['api_endpoint'] . '/deliveryReports/' . $messageId);

            if ($response->successful()) {
                $data = $response->json();
                return $data['deliveryStatus'] ?? null;
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Exception checking delivery status: " . $e->getMessage());
            return false;
        }
    }
}

