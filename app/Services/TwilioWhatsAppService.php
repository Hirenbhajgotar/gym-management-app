<?php

namespace App\Services;

use App\Models\NotificationSetting;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

class TwilioWhatsAppService implements WhatsAppServiceInterface
{
    private string $tenantId;

    public function __construct(string $tenantId)
    {
        $this->tenantId = $tenantId;
    }

    public function sendMessage(string $to, string $message): array
    {
        try {
            $settings = NotificationSetting::where('gym_id', $this->tenantId)->first();

            if (!$settings || $settings->verification_status !== 'verified') {
                throw new \Exception('Twilio account not verified for this gym');
            }

            // Use gym owner's individual Twilio account
            $client = new Client($settings->twilio_account_sid, $settings->twilio_auth_token);

            $twilioMessage = $client->messages->create(
                "whatsapp:$to",
                [
                    'from' => "whatsapp:" . $settings->whatsapp_business_number,
                    'body' => $message
                ]
            );

            return [
                'success' => true,
                'message_id' => $twilioMessage->sid,
                'status' => $twilioMessage->status,
                'from' => $settings->whatsapp_business_number,
                'data' => $twilioMessage->toArray()
            ];
        } catch (RestException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'data' => null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function getAccountBalance(): array
    {
        try {
            $settings = NotificationSetting::where('gym_id', $this->tenantId)->first();

            if (!$settings) {
                throw new \Exception('No Twilio account found');
            }

            $client = new Client($settings->twilio_account_sid, $settings->twilio_auth_token);
            $account = $client->accounts($settings->twilio_account_sid)->fetch();

            return [
                'success' => true,
                'balance' => $account->balance,
                'status' => $account->status
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
