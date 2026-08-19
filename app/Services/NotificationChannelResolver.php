<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\GymSetting;
use App\Notifications\Contracts\HasMemberRecipient;
use App\Notifications\WhatsAppChannel;
use Illuminate\Notifications\Notification;

class NotificationChannelResolver
{
    /**
     * Determine which notification channels to use for a gym.
     *
     * Gate layering: this resolver applies the opt-in gate early (so a
     * member who cannot receive WhatsApp is excluded from the channel
     * list), and WhatsAppChannel::send() re-applies the same gate
     * authoritatively. The rule of record lives on
     * GymMember::canReceiveWhatsappNotifications().
     */
    public static function getChannelsForGym(Gym $gym, NotificationType|string $notificationType = 'general', mixed $notifiable = null): array
    {
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
            $canReceive = true;
            if ($notifiable instanceof GymMember) {
                $canReceive = $notifiable->canReceiveWhatsappNotifications();
            }

            if ($canReceive) {
                $channels[] = WhatsAppChannel::class;
            }
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
     * Resolve the GymMember that a notification is targeting.
     *
     * Single source of truth for "which member", so the opt-in gate cannot
     * drift across the resolver, via(), and channel send() layers.
     */
    public static function resolveTargetMember(mixed $notifiable, Notification $notification): ?GymMember
    {
        if ($notifiable instanceof GymMember) {
            return $notifiable;
        }

        if ($notification instanceof HasMemberRecipient) {
            return $notification->getMember();
        }

        return null;
    }

    /**
     * Check if WhatsApp is properly configured for the gym
     */
    public static function isWhatsAppConfigured(Gym $gym): bool
    {
        $gym->loadMissing('whatsAppAccount');
        $whatsAppAccount = $gym->whatsAppAccount;

        if (! $whatsAppAccount) {
            return false;
        }

        return $whatsAppAccount->isActive()
            && $whatsAppAccount->hasActivePhoneNumber();
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
