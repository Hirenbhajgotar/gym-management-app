<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Gym;
use App\Models\User;
use App\Notifications\Contracts\HasGymContext;
use App\Providers\WhatsAppProviderFactory;
use App\Traits\HasTenantAccess;
use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp channel for sending notifications via WhatsApp.
 *
 * This channel uses WhatsAppProviderFactory to create the appropriate
 * provider based on the configured provider in services.php config.
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

        if (blank($message) || ! isset($message['to']) || (! isset($message['body']) && ! isset($message['template_sid']))) {
            Log::warning('Invalid WhatsApp message format', ['message' => $message]);

            return null;
        }

        // Get the gym/tenant ID
        $gymId = (int) $this->getGymId($notifiable, $notification);

        if (! $gymId) {
            Log::error('Unable to determine gym ID for WhatsApp notification', [
                'notifiable_id' => $notifiable->id,
                'notifiable_type' => get_class($notifiable),
                'notification_type' => get_class($notification),
            ]);

            return null;
        }

        // Check if this is a system message (super-admin) or owner message
        $isSystemMessage = method_exists($notification, 'isSystemMessage')
            ? $notification->isSystemMessage()
            : false;

        // Create provider using factory (based on config and system message flag)
        $provider = WhatsAppProviderFactory::create((int) $gymId, $isSystemMessage);

        $result = [];

        try {
            // Check if this is a template message or plain text
            $isTemplate = isset($message['template_sid']) || isset($message['template_name']);

            if ($isTemplate) {
                $templateNameOrSid = $message['template_name'] ?? $message['template_sid'];
                $result = $provider->sendTemplateMessage(
                    $message['to'],
                    $templateNameOrSid,
                    $message['template_variables'] ?? [],
                );
            } else {
                $result = $provider->sendMessage($message['to'], $message['body']);
            }

            if (! ($result['success'] ?? false)) {
                Log::error('WhatsApp notification failed', [
                    'error' => $result['error'] ?? 'Unknown error',
                    'notifiable_id' => $notifiable->id,
                    'gym_id' => $gymId,
                    'provider' => WhatsAppProviderFactory::getProviderName(),
                ]);
            }
        } catch (Exception $e) {
            Log::error('WhatsApp notification encountered an exception', [
                'error' => $e->getMessage(),
                'notifiable_id' => $notifiable->id,
                'gym_id' => $gymId,
                'provider' => WhatsAppProviderFactory::getProviderName(),
            ]);
        }

        return $result;
    }

    private function getGymId($notifiable, $notification): ?int
    {
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
                    /** @var Gym $gym */
                    $gym = $notifiable->gyms()->first();
                    $gymId = $gym->id;
                }
            }
        }

        if (! $gymId) {
            return null;
        }

        return (int) $gymId;
    }
}
