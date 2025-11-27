<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\NotificationTemplate;
use App\Models\User;
use Filament\Facades\Filament;

class NotificationService
{
    public function sendNotification(
        User $recipient,
        string $type,
        array $channels = ["whatsapp"],
        array $variables = [],
    ): void {
        $tenantId = $recipient->tenant_id ?? Filament::getTenant()->id;
        $gymOwner = $this->getGymOwner($tenantId);

        $settings = NotificationSetting::where("gym_id", $tenantId)
            ->where("gym_owner_id", $gymOwner->id)
            ->first();

        if (!$settings) {
            return;
        }

        foreach ($channels as $channel) {
            if (!$settings->isNotificationEnabled($type, $channel)) {
                continue;
            }

            $template = NotificationTemplate::where("gym_id", $tenantId)
                ->where("type", $type)
                ->where("channel", $channel)
                ->where("is_active", true)
                ->first();

            if (!$template) {
                continue;
            }

            $message = $template->parseTemplate($variables);

            if ($channel === "whatsapp") {
                $this->sendWhatsAppNotification(
                    $recipient,
                    $settings,
                    $type,
                    $message,
                );
            } elseif ($channel === "email") {
                $this->sendEmailNotification($recipient, $template, $variables);
            }
        }
    }

    private function sendWhatsAppNotification(
        User $recipient,
        NotificationSetting $settings,
        string $type,
        string $message,
    ): void {
        if (!$recipient->whatsapp_number) {
            return;
        }

        $whatsappService = $this->getWhatsAppService($settings);
        $result = $whatsappService->sendMessage(
            $recipient->whatsapp_number,
            $message,
        );

        NotificationLog::create([
            "gym_id" => $settings->gym_id,
            "user_id" => $recipient->id,
            "type" => $type,
            "channel" => "whatsapp",
            "status" => $result["success"] ? "sent" : "failed",
            "message" => $message,
            "response_data" => $result["data"],
            "external_id" => $result["message_id"] ?? null,
            "sent_at" => $result["success"] ? now() : null,
        ]);
    }

    private function getWhatsAppService(
        NotificationSetting $settings,
    ): WhatsAppServiceInterface {
        switch ($settings->whatsapp_provider) {
            case "twilio":
                return new TwilioWhatsAppService($settings->gym_id);
            default:
                throw new \InvalidArgumentException(
                    "Unsupported WhatsApp provider: {$settings->whatsapp_provider}",
                );
        }
    }

    public function sendEmailNotification(
        User $recipient,
        NotificationTemplate $template,
        array $variables,
    ): void {
        // Implement email notification logic here
        // You can use Laravel's Mail facade
    }

    public function getGymOwner(string $tenantId): User
    {
        return User::where("tenant_id", $tenantId)
            ->whereHas("roles", function ($query) {
                $query->where("name", "gym_owner");
            })
            ->first();
    }
}
