<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Contracts\HasGymContext;
use App\Services\TwilioWhatsAppService;
use Exception;
use Filament\Facades\Filament;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    public function send($notifiable, Notification $notification)
    {
        // Get the WhatsApp message from the notification
        $message = $notification->toWhatsApp($notifiable);

        if (!$message) {
            return;
        }

        // Get the gym/tenant ID
        $gymId = null;

        // 1. Check for a getGymId method on the notification
        if ($notification instanceof HasGymContext) {
            $gymId = $notification->getGymId($notifiable);
        }

        // 2. Check the notifiable itself
        if (!$gymId) {
            if (isset($notifiable->gym_id)) {
                $gymId = $notifiable->gym_id;
            } elseif (method_exists($notifiable, 'gym') && $notifiable->gym) {
                $gymId = $notifiable->gym->id;
            }
        }

        // 3. Handle User model with tenants as a fallback
        if (!$gymId && $notifiable instanceof User) {
            if (class_exists(Filament::class) && Filament::isServing() && ($tenant = Filament::getTenant())) {
                $gymId = $tenant->id;
            } elseif ($notifiable->gyms()->count() === 1) {
                $gymId = $notifiable->gyms()->first()->id;
            }
        }

        if (!$gymId) {
            Log::error("Unable to determine gym ID for WhatsApp notification", [
                'notifiable_id' => $notifiable->id,
                'notifiable_type' => get_class($notifiable),
                'notification_type' => get_class($notification),
            ]);

            throw new Exception(
                "Unable to determine gym ID for WhatsApp notification. Notifiable: " . get_class($notifiable) . ", Notification: " . get_class($notification)
            );
        }

        // Initialize Twilio service
        $twilioService = new TwilioWhatsAppService($gymId);

        // Check if this is a template message or plain text
        $isTemplate = isset($message["template_sid"]);

        $result = $isTemplate
            ? $twilioService->sendTemplateMessage(
                $message["to"],
                $message["template_sid"],
                $message["template_variables"] ?? [],
            )
            : $twilioService->sendMessage($message["to"], $message["body"]);

        if (!$result["success"]) {
            Log::error("WhatsApp notification failed", [
                "error" => $result["error"] ?? "Unknown error",
                "notifiable_id" => $notifiable->id,
            ]);

            // Optionally throw exception to retry via queue
            // throw new \Exception($result['error']);
        }

        return $result;
    }
}
