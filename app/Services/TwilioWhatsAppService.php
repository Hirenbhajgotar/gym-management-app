<?php

namespace App\Services;

use App\Enums\NotificationVerificationStatusEnum;
use App\Models\NotificationSetting;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

class TwilioWhatsAppService implements WhatsAppServiceInterface
{
    private string $tenantId;
    private ?Client $client;

    public function __construct(string $tenantId, ?Client $client = null)
    {
        $this->tenantId = $tenantId;
        $this->client = $client;
    }

    private function getClient(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        $settings = NotificationSetting::where('gym_id', $this->tenantId)->first();

        if (!$settings) {
            throw new \Exception('No Twilio settings found');
        }

        return new Client($settings->twilio_account_sid, $settings->twilio_auth_token);
    }

    public function sendMessage(string $to, string $message): array
    {
        try {
            $settings = NotificationSetting::where('gym_id', $this->tenantId)->first();

            if (!$settings) {
                throw new \Exception('No Twilio settings found for this gym');
            }

            // Fix: Compare with enum value instead of string
            if ($settings->verification_status !== NotificationVerificationStatusEnum::VERIFIED) {
                throw new \Exception('Twilio account not verified for this gym. Status: ' . $settings->verification_status->value);
            }

            $client = $this->getClient();

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

            $client = $this->getClient();
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
