<?php

namespace App\Services;

use App\Enums\NotificationVerificationStatusEnum;
use App\Models\Gym;
use App\Models\GymSetting;
use App\Notifications\WhatsAppChannel;

class NotificationChannelResolver
{
    /**
     * Determine which notification channels to use for a gym
     */
    public static function getChannelsForGym(Gym $gym, string $notificationType = 'general'): array
    {
        // $plan = $gym->subscription?->plan ?? 'free';
        $gymId = $gym->id;
        $emailEnabled = self::getSetting($gymId, 'email_notifications', true);
        $whatsappEnabled = self::getSetting($gymId, 'whatsapp_notifications', false);
        $smsEnabled = self::getSetting($gymId, 'sms_notifications', false);
        $notificationTypeEnabled = self::getSetting($gymId, $notificationType . '_notifications', true);

        // If the specific notification type is disabled, return empty array
        if (!$notificationTypeEnabled) {
            return [];
        }

        $channels = [];

        // switch ($plan) {
        //     case 'gold':
        //         // Gold plan: WhatsApp if configured, fallback to email
        //         if (self::canSendWhatsApp($gym)) {
        //             $channels[] = WhatsAppChannel::class;
        //         } else {
        //             $channels[] = 'mail';
        //         }
        //         break;

        //     case 'silver':
        //         // Silver plan: Email only
        //         $channels[] = 'mail';
        //         break;

        //     case 'bronze':
        //     case 'free':
        //     default:
        //         // Free/Bronze: Email only (or no notifications)
        //         $channels[] = 'mail';
        //         break;
        // }
        // $channels[] = WhatsAppChannel::class;
        // $channels[] = 'mail';

        // Add channels based on settings
        if ($whatsappEnabled) {
            $channels[] = WhatsAppChannel::class;
        }

        if ($smsEnabled) {
            $channels[] = 'sms';
        }

        // Always include email as fallback if no other channels are enabled
        if (empty($channels) || $emailEnabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    private static function getSetting(int $gymId, string $key, $default = null)
    {
        $setting = GymSetting::where('gym_id', $gymId)
            ->where('key', $key)
            ->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Check if gym can send WhatsApp messages
     */
    private static function canSendWhatsApp(Gym $gym): bool
    {
        $settings = $gym->notificationSettings()->first();

        if (!$settings) {
            return false;
        }

        return $settings->verification_status === NotificationVerificationStatusEnum::VERIFIED
            && !empty($settings->twilio_account_sid)
            && !empty($settings->twilio_auth_token);
    }
}
