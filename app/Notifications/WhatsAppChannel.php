<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Contracts\HasGymContext;
use App\Services\TwilioWhatsAppService;
use App\Traits\HasTenantAccess;
use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp channel for sending notifications via WhatsApp.
 */
class WhatsAppChannel
{
    use HasTenantAccess;

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @return array<string, mixed>|null
     */
    public function send($notifiable, Notification $notification)
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return null;
        }

        // Get the WhatsApp message from the notification
        $message = $notification->toWhatsApp($notifiable);

        if (! $message) {
            return null;
        }

        // Get the gym/tenant ID
        $gymId = null;

        // 1. Check for a getGymId method on the notification
        if ($notification instanceof HasGymContext) {
            $gymId = $notification->getGymId($notifiable);
        }

        // 2. Check the notifiable itself
        if (! $gymId) {
            if (isset($notifiable->gym_id)) {
                $gymId = $notifiable->gym_id;
            } elseif (method_exists($notifiable, 'gym') && is_object($notifiable) && property_exists($notifiable, 'gym')) {
                if ($notifiable->gym) {
                    $gymId = $notifiable?->gym?->id;
                }
            }
        }

        // 3. Handle User model with tenants as a fallback
        if (! $gymId && $notifiable instanceof User) {
            try {
                $tenant = $this->getGym();
                $gymId = $tenant?->id;
            } catch (\Exception $e) {
                // No tenant set, check if user has exactly one gym
                if ($notifiable->gyms()->count() === 1) {
                    /** @var \App\Models\Gym $gym */
                    $gym = $notifiable->gyms()->first();
                    $gymId = $gym->id;
                }
            }
        }

        if (! $gymId) {
            Log::error('Unable to determine gym ID for WhatsApp notification', [
                'notifiable_id' => $notifiable->id,
                'notifiable_type' => get_class($notifiable),
                'notification_type' => get_class($notification),
            ]);

            throw new Exception(
                'Unable to determine gym ID for WhatsApp notification. Notifiable: ' . get_class($notifiable) . ', Notification: ' . get_class($notification)
            );
        }

        // Initialize Twilio service
        $twilioService = new TwilioWhatsAppService($gymId);

        // Check if this is a template message or plain text
        $isTemplate = isset($message['template_sid']);

        $result = $isTemplate
            ? $twilioService->sendTemplateMessage(
                $message['to'],
                $message['template_sid'],
                $message['template_variables'] ?? [],
            )
            : $twilioService->sendMessage($message['to'], $message['body']);

        if (! $result['success']) {
            Log::error('WhatsApp notification failed', [
                'error' => $result['error'] ?? 'Unknown error',
                'notifiable_id' => $notifiable->id,
            ]);

            // Optionally throw exception to retry via queue
            // throw new \Exception($result['error']);
        }

        return $result;
    }
}
