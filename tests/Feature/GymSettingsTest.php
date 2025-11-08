<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\GymSettings;
use App\Models\Gym;
use App\Models\GymSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GymSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function when_whatsapp_notifications_is_enabled_all_specific_whatsapp_notification_types_are_enabled_based_on_database()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $gym = Gym::factory()->create();
        Filament::setTenant($gym);

        GymSetting::create(['gym_id' => $gym->id, 'key' => 'membership_expiry_notifications', 'value' => 'true', 'type' => 'boolean']);
        GymSetting::create(['gym_id' => $gym->id, 'key' => 'birthday_notifications', 'value' => 'false', 'type' => 'boolean']);
        GymSetting::create(['gym_id' => $gym->id, 'key' => 'attendance_reminders', 'value' => 'true', 'type' => 'boolean']);
        GymSetting::create(['gym_id' => $gym->id, 'key' => 'payment_reminders', 'value' => 'false', 'type' => 'boolean']);
        GymSetting::create(['gym_id' => $gym->id, 'key' => 'followup_reminders', 'value' => 'true', 'type' => 'boolean']);
        GymSetting::create(['gym_id' => $gym->id, 'key' => 'lead_reminders_notifications', 'value' => 'true', 'type' => 'boolean']);

        Livewire::test(GymSettings::class)
            ->fillForm([
                'whatsapp_notifications' => true,
            ])
            ->assertFormSet([
                'membership_expiry_notifications' => true,
                'birthday_notifications' => false,
                'attendance_reminders' => true,
                'payment_reminders' => false,
                'followup_reminders' => true,
                'lead_reminders_notifications' => true,
            ]);
    }

    /** @test */
    public function when_whatsapp_notifications_is_disabled_all_specific_whatsapp_notification_types_are_disabled()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $gym = Gym::factory()->create();
        Filament::setTenant($gym);

        Livewire::test(GymSettings::class)
            ->fillForm([
                'whatsapp_notifications' => false,
            ])
            ->assertFormSet([
                'membership_expiry_notifications' => false,
                'birthday_notifications' => false,
                'attendance_reminders' => false,
                'payment_reminders' => false,
                'followup_reminders' => false,
                'lead_reminders_notifications' => false,
            ]);
    }
}
