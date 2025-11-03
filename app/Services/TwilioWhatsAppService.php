<?php

namespace App\Services;

use App\Enums\NotificationVerificationStatusEnum;
use App\Models\GymWhatsAppBalance;
use App\Models\NotificationSetting;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

class TwilioWhatsAppService implements WhatsAppServiceInterface
{
    private string $tenantId;

    private ?Client $client;

    private bool $useSandbox = true; // Set to true for testing

    public function __construct(string $tenantId, ?Client $client = null)
    {
        $this->tenantId = $tenantId;
        $this->client = $client;
        $this->useSandbox = config("services.twilio.use_sandbox", true);
    }

    private function getClient(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        // For testing, use master account credentials
        if ($this->useSandbox) {
            return new Client(
                config("services.twilio.master_sid"),
                config("services.twilio.master_token"),
            );
        }

        $settings = NotificationSetting::where(
            "gym_id",
            $this->tenantId,
        )->first();

        if (!$settings) {
            throw new \Exception("No Twilio settings found");
        }

        return new Client(
            $settings->twilio_account_sid,
            $settings->twilio_auth_token,
        );
    }

    public function sendMessage(
        string $to,
        string $message,
        ?array $templateData = null,
    ): array {
        try {
            $settings = NotificationSetting::where(
                "gym_id",
                $this->tenantId,
            )->first();

            if (!$settings) {
                throw new \Exception("No Twilio settings found for this gym");
            }

            // Fix: Compare with enum value instead of string
            if (
                $settings->verification_status !==
                NotificationVerificationStatusEnum::VERIFIED
            ) {
                throw new \Exception(
                    "Twilio account not verified for this gym. Status: " .
                        $settings->verification_status->value,
                );
            }

            // Check if Twilio account is active
            // if ($settings->status !== NotificationVerificationStatusEnum::ACTIVE) {
            //     throw new \Exception(
            //         "Twilio account not active for this gym. Status: " .
            //             $settings->status->value,
            //     );
            // }
            //

            // Check WhatsApp message balance
            $balance = GymWhatsAppBalance::where(
                "gym_id",
                $this->tenantId,
            )->first();
            if (!$balance || !$balance->canSendMessage()) {
                throw new \Exception(
                    "Insufficient WhatsApp message balance. Please purchase more messages.",
                );
            }

            $client = $this->getClient();

            // Get from number based on sandbox or production
            $fromNumber = $this->useSandbox
                ? config("services.twilio.sandbox_number") // e.g., '+14155238886'
                : $this->getProductionFromNumber($settings);

            $messageParams = [
                "from" => "whatsapp:" . $fromNumber,
            ];

            if ($templateData && isset($templateData["template_sid"])) {
                $messageParams["contentSid"] = $templateData["template_sid"];
                $messageParams["contentVariables"] = json_encode(
                    $templateData["template_variables"] ?? [],
                );
            } else {
                $messageParams["body"] = $message;
            }

            $twilioMessage = $client->messages->create(
                "whatsapp:$to",
                $messageParams,
            );

            // Increment messages used counter after successful sending
            $balance->increment("messages_used");

            return [
                "success" => true,
                "message_id" => $twilioMessage->sid,
                "status" => $twilioMessage->status,
                "from" => $fromNumber,
                "data" => $twilioMessage->toArray(),
            ];
        } catch (RestException $e) {
            return [
                "success" => false,
                "error" => $e->getMessage(),
                "code" => $e->getCode(),
                "data" => null,
            ];
        } catch (\Exception $e) {
            return [
                "success" => false,
                "error" => $e->getMessage(),
                "data" => null,
            ];
        }
    }

    /**
     * Send a WhatsApp message using an approved template
     */
    public function sendTemplateMessage(
        string $to,
        string $templateSid,
        array $variables = [],
    ): array {
        try {
            $settings = NotificationSetting::where(
                "gym_id",
                $this->tenantId,
            )->first();

            if (!$settings) {
                throw new \Exception("No Twilio settings found for this gym");
            }

            if (
                $settings->verification_status !==
                NotificationVerificationStatusEnum::VERIFIED
            ) {
                throw new \Exception(
                    "Twilio account not verified for this gym. Status: " .
                        $settings->verification_status->value,
                );
            }

            // Check WhatsApp message balance
            $balance = GymWhatsAppBalance::where(
                "gym_id",
                $this->tenantId,
            )->first();
            if (!$balance || !$balance->canSendMessage()) {
                throw new \Exception(
                    "Insufficient WhatsApp message balance. Please purchase more messages.",
                );
            }

            $client = $this->getClient();

            // Get from number (production only, templates don't work in sandbox)
            $fromNumber = $this->getProductionFromNumber($settings);

            // Send message with template
            $twilioMessage = $client->messages->create("whatsapp:$to", [
                "from" => "whatsapp:" . $fromNumber,
                "contentSid" => $templateSid,
                "contentVariables" => json_encode($variables),
            ]);

            // Increment messages used counter after successful sending
            $balance->increment("messages_used");

            return [
                "success" => true,
                "message_id" => $twilioMessage->sid,
                "status" => $twilioMessage->status,
                "from" => $fromNumber,
                "template_sid" => $templateSid,
                "data" => $twilioMessage->toArray(),
            ];
        } catch (RestException $e) {
            return [
                "success" => false,
                "error" => $e->getMessage(),
                "code" => $e->getCode(),
                "data" => null,
            ];
        } catch (\Exception $e) {
            return [
                "success" => false,
                "error" => $e->getMessage(),
                "data" => null,
            ];
        }
    }

    public function getProductionFromNumber(
        ?NotificationSetting $settings,
    ): ?string {
        if (!$settings) {
            $settings = NotificationSetting::where(
                "gym_id",
                $this->tenantId,
            )->first();
        }

        if (!$settings) {
            throw new \Exception("No Twilio settings found");
        }

        if (
            $settings->verification_status !==
            NotificationVerificationStatusEnum::VERIFIED
        ) {
            throw new \Exception("Twilio account not verified");
        }

        return $settings->whatsapp_business_number;
    }

    public function getAccountBalance(): array
    {
        try {
            $settings = NotificationSetting::where(
                "gym_id",
                $this->tenantId,
            )->first();

            if (!$settings) {
                throw new \Exception("No Twilio account found");
            }

            $client = $this->getClient();

            $accountSid = $this->useSandbox
                ? config("services.twilio.master_sid")
                : $settings->twilio_account_sid;

            $account = $client->accounts($accountSid)->fetch();

            return [
                "success" => true,
                "balance" =>
                    $account->balance ?? "Trial Account - No Balance Info",
                "status" => $account->status,
            ];
        } catch (\Exception $e) {
            return [
                "success" => false,
                "error" => $e->getMessage(),
            ];
        }
    }
}
