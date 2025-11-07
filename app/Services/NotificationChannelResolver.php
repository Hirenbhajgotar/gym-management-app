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
    public static function getChannelsForGym(
        Gym $gym,
        string $notificationType = "general",
    ): array {
        // $plan = $gym->subscription?->plan ?? 'free';

        $settings = self::getSetting($gym->id);

        $emailEnabled = $settings->get("email_notifications")?->value ?? true;

        $whatsappEnabled = $settings->get("whatsapp_notifications")?->value
            ? true
            : false;

        $smsEnabled = $settings->get("sms_notifications")?->value
            ? true
            : false;

        $notificationTypeEnabled = $settings->get(
            "{$notificationType}_notifications",
        )->value
            ? true
            : false;

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
            $channels[] = "sms";
        }

        // Always include email as fallback if no other channels are enabled
        if (empty($channels) || $emailEnabled) {
            $channels[] = "mail";
        }

        return $channels;
    }

    private static function getSetting(int $gymId)
    {
        return GymSetting::where("gym_id", $gymId)->get()->keyBy("key");
    }
}
