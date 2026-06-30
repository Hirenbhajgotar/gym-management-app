<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Gym;
use App\Models\GymSetting;
use App\Notifications\WhatsAppChannel;

class NotificationChannelResolver
{
    /**
     * Determine which notification channels to use for a gym
     */
    public static function getChannelsForGym(
        Gym $gym,
        NotificationType|string $notificationType = 'general',
    ): array {
        $settings = self::getSetting($gym->id);
        $notificationTypeString = $notificationType instanceof NotificationType
            ? $notificationType->value
            : $notificationType;

        $emailEnabled = (bool) ($settings->get('email_notifications')?->value ?? true);

        // Check if WhatsApp notifications are globally enabled for the gym
        $whatsappEnabled = (bool) ($settings->get('whatsapp_notifications')?->value ?? false);

        $smsEnabled = (bool) ($settings->get('sms_notifications')?->value ?? false);

        // Check if specific notification type is enabled
        $notificationTypeEnabled = (bool) ($settings->get($notificationTypeString)?->value ?? false);

        // If the specific notification type is disabled, return empty array for notifications completely
        if (! $notificationTypeEnabled && $notificationTypeString !== 'general') {
            return [];
        }

        $channels = [];

        // Add channels based on settings
        if ($whatsappEnabled && self::isWhatsAppConfigured($gym)) {
            $channels[] = WhatsAppChannel::class;
        }

        if ($smsEnabled) {
            $channels[] = 'sms';
        }

        // Always include email as primary channel
        if ($emailEnabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Check if WhatsApp is properly configured for the gym
     */
    public static function isWhatsAppConfigured(Gym $gym): bool
    {
        $whatsAppAccount = $gym?->whatsAppAccount;

        if (! $whatsAppAccount) {
            return false;
        }

        return $whatsAppAccount->isActive()
            && $whatsAppAccount->isVerified()
            && $whatsAppAccount->hasPhoneNumber();
    }

    /**
     * Check if a specific notification type is enabled for a gym
     */
    public static function isNotificationEnabled(Gym $gym, NotificationType|string $type): bool
    {
        $settings = self::getSetting($gym->id);
        $typeString = $type instanceof NotificationType ? $type->value : $type;

        return (bool) ($settings->get($typeString)?->value ?? false);
    }

    private static function getSetting(int $gymId)
    {
        return GymSetting::query()->where('gym_id', $gymId)->get()->keyBy('key');
    }
}
