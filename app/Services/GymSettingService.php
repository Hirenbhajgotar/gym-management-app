<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\GymSetting;

class GymSettingService
{
    public function createDefaultSettings(Gym $gym): void
    {
        $settings = [
            'whatsapp_notifications' => true,
            'birthday_notifications' => true,
            'payment_reminders' => true,
            'membership_expiry_notifications' => true,
            'followup_reminders' => true,
            'lead_reminders_notifications' => true,
            'enable_low_balance_alerts' => true,
        ];

        foreach ($settings as $key => $value) {
            GymSetting::query()->updateOrCreate(['gym_id' => $gym->id, 'key' => $key], ['value' => $value, 'type' => gettype($value)]);
        }
    }
}
