<?php

namespace App\Services;

use App\Enums\NotificationVerificationStatusEnum;
use App\Models\Gym;
use App\Notifications\WhatsAppChannel;

class NotificationChannelResolver
{
    /**
     * Determine which notification channels to use for a gym
     */
    public static function getChannelsForGym(Gym $gym, string $notificationType = 'general'): array
    {
        // $plan = $gym->subscription?->plan ?? 'free';
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
        $channels[] = 'mail';

        return $channels;
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
