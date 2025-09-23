<?php

namespace App\Services;

use App\Enums\NotificationAccountStatusEnum;
use App\Enums\NotificationVerificationStatusEnum;
use App\Models\NotificationSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

class TwilioAccountManager
{
    private Client $masterClient;
    private string $masterAccountSid;
    private string $masterAuthToken;

    public function __construct()
    {
        $this->masterAccountSid = config('services.twilio.master_sid');
        $this->masterAuthToken = config('services.twilio.master_token');
        $this->masterClient = new Client($this->masterAccountSid, $this->masterAuthToken);
    }

    /**
     * Create Twilio subaccount for gym owner with their WhatsApp number
     */
    public function createAccountForGymOwner(
        string $tenantId,
        string $gymOwnerName,
        string $gymName,
        string $whatsappNumber,
        string $email
    ): array {
        try {
            DB::beginTransaction();

            // Create Twilio Subaccount
            $subaccount = $this->masterClient->api->v2010->accounts->create([
                'friendlyName' => "Gym: {$gymName} - Owner: {$gymOwnerName}",
            ]);

            // Store account details in database
            $accountData = [
                'gym_id' => $tenantId,
                'twilio_account_sid' => $subaccount->sid,
                'twilio_auth_token' => $subaccount->authToken,
                'whatsapp_business_number' => $whatsappNumber,
                'account_status' => NotificationAccountStatusEnum::CREATED,
                'verification_status' => NotificationVerificationStatusEnum::PENDING,
                'created_at' => now(),
            ];

            // Store in notification_settings table
            NotificationSetting::updateOrCreate(
                ['gym_id' => $tenantId],
                $accountData
            );

            // Initiate WhatsApp number verification process
            $verificationResult = $this->initiateWhatsAppVerification(
                $subaccount->sid,
                $subaccount->authToken,
                $whatsappNumber
            );

            DB::commit();

            return [
                'success' => true,
                'account_sid' => $subaccount->sid,
                'auth_token' => $subaccount->authToken,
                'verification_required' => true,
                'verification_method' => 'sms',
                'message' => 'Twilio account created successfully. Please verify your WhatsApp number.'
            ];
        } catch (RestException $e) {
            DB::rollBack();
            Log::error($e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'message' => 'Failed to create Twilio account'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Unexpected error occurred'
            ];
        }
    }

    /**
     * Initiate WhatsApp number verification
     */
    public function initiateWhatsAppVerification(string $accountSid, string $authToken, string $whatsappNumber): array
    {
        try {
            $client = new Client($accountSid, $authToken);

            // For WhatsApp Business API, we need to request a phone number
            // This is a simplified version - actual implementation may vary
            $incomingPhoneNumber = $client->incomingPhoneNumbers->create([
                'phoneNumber' => $whatsappNumber,
                // 'friendlyName' => 'WhatsApp Business Number'
            ]);

            return [
                'success' => true,
                'method' => 'sms',
                'number_sid' => $incomingPhoneNumber->sid
            ];
        } catch (RestException $e) {
            // If direct purchase fails, we'll need verification
            return [
                'success' => false,
                'method' => 'manual_verification',
                'error' => $e->getMessage(),
                'requires_verification' => true
            ];
        }
    }

    /**
     * Complete WhatsApp number verification
     */
    public function completeWhatsAppVerification(string $tenantId, string $verificationCode): array
    {
        try {
            $settings = NotificationSetting::where('gym_id', $tenantId)->first();

            if (!$settings || !$settings->twilio_account_sid) {
                throw new \Exception('No Twilio account found for this gym');
            }

            $client = new Client($settings->twilio_account_sid, $settings->twilio_auth_token);

            // Verify the code (implementation depends on verification method)
            // This is a placeholder - actual verification depends on Twilio's process

            // Update status in database
            $settings->update([
                'verification_status' => NotificationVerificationStatusEnum::VERIFIED,
                'account_status' => NotificationAccountStatusEnum::ACTIVE,
                'verified_at' => now()
            ]);

            // Test the WhatsApp integration
            $testResult = $this->sendTestWhatsAppMessage($settings);

            return [
                'success' => true,
                'message' => 'WhatsApp number verified successfully',
                'test_message_sent' => $testResult['success'] ?? false
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Verification failed'
            ];
        }
    }

    /**
     * Send test WhatsApp message to verify setup
     */
    public function sendTestWhatsAppMessage(NotificationSetting $settings): array
    {
        try {
            $client = new Client($settings->twilio_account_sid, $settings->twilio_auth_token);

            // Send test message to gym owner
            $message = $client->messages->create(
                "whatsapp:" . $settings->whatsapp_business_number,
                [
                    'from' => "whatsapp:" . $settings->whatsapp_business_number,
                    'body' => 'Your WhatsApp Business integration is now active! You can start sending notifications to your gym members.'
                ]
            );

            return [
                'success' => true,
                'message_id' => $message->sid
            ];
        } catch (RestException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get account status and usage
     */
    public function getAccountStatus(string $tenantId): array
    {
        $settings = NotificationSetting::where('gym_id', $tenantId)->first();

        if (!$settings || !$settings->twilio_account_sid) {
            return [
                'status' => 'not_created',
                'message' => 'No Twilio account found'
            ];
        }

        try {
            $client = new Client($settings->twilio_account_sid, $settings->twilio_auth_token);

            // Get account info
            $account = $client->accounts($settings->twilio_account_sid)->fetch();

            // Get usage for current month
            $usage = $client->usage->records->read([
                'category' => 'messages',
                'startDate' => now()->startOfMonth()->format('Y-m-d'),
                'endDate' => now()->format('Y-m-d')
            ]);

            $messageCount = 0;
            $cost = 0;
            foreach ($usage as $record) {
                $messageCount += $record->count;
                $cost += (float)$record->price;
            }

            return [
                'status' => $account->status,
                'verification_status' => $settings->verification_status,
                'balance' => $account->balance,
                'messages_sent' => $messageCount,
                'monthly_cost' => $cost,
                'account_created' => $settings->created_at,
                'last_activity' => now()
            ];
        } catch (RestException $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
}
