<?php

namespace App\Services;

use App\Enums\NotificationVerificationStatusEnum;
use App\Models\GymWhatsAppBalance;
use App\Models\NotificationSetting;
use Illuminate\Support\Facades\Log;
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

    /**
     * Check if this is a system tenant (super-admin, no tenant association)
     */
    private function isSystemTenant(): bool
    {
        return $this->tenantId === config("app.system_tenant_id", "system") ||
            $this->tenantId === "system";
    }

    private function getClient(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        // For system tenant or testing, use master account credentials
        if ($this->useSandbox || $this->isSystemTenant()) {
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
            // For system tenant (super-admin), bypass tenant-specific checks
            if ($this->isSystemTenant()) {
                return $this->sendSystemMessage($to, $message, $templateData);
            }

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
     * Send message for system tenant (super-admin) without tenant checks
     * This is used for users who don't belong to any tenant (e.g., super-admin)
     *
     * In sandbox mode: Uses free-form message body
     * In production mode: Uses approved WhatsApp template
     */
    private function sendSystemMessage(
        string $to,
        string $message,
        ?array $templateData = null,
    ): array {
        try {
            $client = $this->getClient();

            // Get from number based on sandbox or production
            $fromNumber = $this->useSandbox
                ? config("services.twilio.sandbox_number")
                : config("services.twilio.sandbox_number"); // System tenant typically uses sandbox

            $messageParams = [
                "from" => "whatsapp:" . $fromNumber,
            ];

            // In sandbox mode, we can send free-form messages
            // In production mode, we MUST use approved templates
            if ($this->useSandbox) {
                // Sandbox: Use free-form message body
                if ($templateData && isset($templateData["template_sid"])) {
                    $messageParams["contentSid"] =
                        $templateData["template_sid"];
                    $messageParams["contentVariables"] = json_encode(
                        $templateData["template_variables"] ?? [],
                    );
                } else {
                    $messageParams["body"] = $message;
                }
            } else {
                // Production: Use approved template (required for business-initiated messages)
                // Check if template data provided or get from config
                if ($templateData && isset($templateData["template_sid"])) {
                    $messageParams["contentSid"] =
                        $templateData["template_sid"];
                    $messageParams["contentVariables"] = json_encode(
                        $templateData["template_variables"] ?? [],
                    );
                } else {
                    // Try to get demo request template from config
                    $templateSid = config(
                        "services.twilio.demo_request_template_sid",
                    );

                    if (!$templateSid) {
                        // No template available - return helpful error
                        return [
                            "success" => false,
                            "error" =>
                                "No approved WhatsApp template found for demo requests. Please:\n" .
                                "1. Create and approve a 'demo_request' template in Twilio console, OR\n" .
                                "2. Set TWILIO_DEMO_REQUEST_TEMPLATE_SID in your .env file, OR\n" .
                                "3. Use sandbox mode (TWILIO_USE_SANDBOX=true) for testing.",
                            "requires_template" => true,
                            "mode" => "production",
                        ];
                    }

                    // Use template with variables
                    $messageParams["contentSid"] = $templateSid;
                    $messageParams["contentVariables"] = json_encode([
                        "1" => $message, // Pass the entire formatted message as variable
                    ]);

                    Log::info("Using WhatsApp template for system message", [
                        "template_sid" => $templateSid,
                        "to" => $to,
                    ]);
                }
            }

            $twilioMessage = $client->messages->create(
                "whatsapp:$to",
                $messageParams,
            );

            Log::info("System WhatsApp message sent successfully", [
                "message_id" => $twilioMessage->sid,
                "to" => $to,
                "status" => $twilioMessage->status,
                "mode" => $this->useSandbox ? "sandbox" : "production",
                "uses_template" => !$this->useSandbox,
            ]);

            return [
                "success" => true,
                "message_id" => $twilioMessage->sid,
                "status" => $twilioMessage->status,
                "from" => $fromNumber,
                "system_message" => true,
                "mode" => $this->useSandbox ? "sandbox" : "production",
                "data" => $twilioMessage->toArray(),
            ];
        } catch (RestException $e) {
            Log::error("System WhatsApp message error", [
                "to" => $to,
                "error" => $e->getMessage(),
                "code" => $e->getCode(),
                "mode" => $this->useSandbox ? "sandbox" : "production",
            ]);

            // Provide helpful error message for common production mode issues
            $errorMessage = $e->getMessage();
            if (
                !$this->useSandbox &&
                strpos($errorMessage, "template") !== false
            ) {
                $errorMessage .=
                    " - Business-initiated messages require approved templates. Please create a 'demo_request' template or use sandbox mode.";
            }

            return [
                "success" => false,
                "error" => $errorMessage,
                "code" => $e->getCode(),
                "mode" => $this->useSandbox ? "sandbox" : "production",
                "data" => null,
            ];
        } catch (\Exception $e) {
            Log::error("Unexpected system WhatsApp message error", [
                "to" => $to,
                "error" => $e->getMessage(),
                "mode" => $this->useSandbox ? "sandbox" : "production",
            ]);

            return [
                "success" => false,
                "error" => $e->getMessage(),
                "mode" => $this->useSandbox ? "sandbox" : "production",
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
            // System tenant cannot use templates (requires verified business account)
            if ($this->isSystemTenant()) {
                throw new \Exception(
                    "Template messages are not available for system tenant. Use regular messages instead.",
                );
            }

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
            // System tenant uses master account
            if ($this->isSystemTenant()) {
                $client = $this->getClient();
                $accountSid = config("services.twilio.master_sid");

                $account = $client->accounts($accountSid)->fetch();

                return [
                    "success" => true,
                    "balance" =>
                        $account->balance ?? "Trial Account - No Balance Info",
                    "status" => $account->status,
                    "system_account" => true,
                ];
            }

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
