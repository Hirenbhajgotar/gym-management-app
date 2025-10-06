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
            'membership_expiry_notifications' => true,
            'payment_reminders' => true,
        ];

        foreach ($settings as $key => $value) {
            GymSetting::updateOrCreate(
                ['gym_id' => $gym->id, 'key' => $key],
                ['value' => $value, 'type' => gettype($value)]
            );
        }
    }
}
