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
            return [
                'success' => false,
                'message' => 'Business unit not found'
            ];
        }

        if (!$businessUnit->sms_sender_number) {
            Log::error("SMS sender number not configured for business unit: {$businessUnitId}");
            return [
                'success' => false,
                'message' => 'SMS sender number not configured for this business unit'
            ];
        }

        // Check SMS balance first
        if (!AccountSms::checkBalance($businessUnitId)) {
            Log::error("Insufficient SMS balance for business unit: {$businessUnitId}");
            return [
                'success' => false,
                'message' => 'Insufficient SMS balance'
            ];
        }

        // Get SMS configuration
        $config = SmsConfiguration::getActive();
        if (!$config || !SmsConfiguration::isConfigured()) {
            Log::error("SMS configuration not found or incomplete");
            return [
                'success' => false,
                'message' => 'SMS configuration not found'
            ];
        }

        $credentials = $config->getCredentials();

        // Get access token
        $token = $this->getAccessToken($credentials);
        
        if (!$token) {
            Log::error("Failed to get SMS access token");
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

        try {
            // Send SMS using Orange API v1 endpoint
            $response = Http::withHeaders($headers)
                ->post("https://api.orange.com/smsmessaging/v1/outbound/tel:+" . urlencode($senderNumber) . "/requests", $smsData);

            $responseData = $response->json();
            
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

                Log::error("SMS sending failed: " . $response->status() . " - " . $errorMessage);
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

            Log::error("Exception in SMS sending: " . $e->getMessage());
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
     * Get access token from Orange API using Basic authentication
     */
    private function getAccessToken($credentials)
    {
        // If JWT token is provided and still valid, use it
        if (!empty($credentials['jwt_token'])) {
            // TODO: Validate token expiration if needed
            return $credentials['jwt_token'];
        }

        // Get token using Basic authentication with client credentials
        try {
            $authString = base64_encode($credentials['client_id'] . ':' . $credentials['client_secret']);
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $authString,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post('https://api.orange.com/oauth/v3/token', [
                'grant_type' => 'client_credentials',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? null;
            }

            Log::error("Failed to get access token: " . $response->status() . " - " . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error("Exception getting access token: " . $e->getMessage());
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

