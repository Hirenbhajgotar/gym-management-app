<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Models\Gym;
use App\Models\GymSetting;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Notifications\WhatsAppChannel;
use App\Services\NotificationChannelResolver;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the lazy-loading violation raised when
 * NotificationChannelResolver accessed the `whatsAppAccount` relation on a Gym
 * that had not eager loaded it. Prevention is active in non-production, so the
 * test env surfaces any unloaded access as a LazyLoadingViolationException.
 */
class NotificationChannelResolverTest extends TestCase
{
    use RefreshDatabase;

    private function createConfiguredWhatsAppAccount(Gym $gym): WhatsAppAccount
    {
        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+14155551234',
            'formatted_number' => '(415) 555-1234',
            'twilio_phone_number_sid' => 'PNtest123',
            'verification_status' => 'verified',
            'country_code' => 'US',
        ]);

        return $account;
    }

    public function test_get_channels_for_gym_does_not_trigger_lazy_loading_violation(): void
    {
        $gym = Gym::factory()->create();

        GymSetting::query()->create([
            'gym_id' => $gym->id,
            'key' => 'whatsapp_notifications',
            'value' => true,
            'type' => 'boolean',
        ]);

        $this->createConfiguredWhatsAppAccount($gym);

        // Deliberately not eager-loaded, mirroring the production bug.
        $channels = NotificationChannelResolver::getChannelsForGym($gym, 'general');

        $this->assertContains(WhatsAppChannel::class, $channels);
        $this->assertContains('mail', $channels);
    }

    public function test_is_whatsapp_configured_returns_false_without_account_and_no_exception(): void
    {
        $gym = Gym::factory()->create();

        GymSetting::query()->create([
            'gym_id' => $gym->id,
            'key' => 'whatsapp_notifications',
            'value' => true,
            'type' => 'boolean',
        ]);

        $this->assertFalse(
            NotificationChannelResolver::isWhatsAppConfigured($gym),
        );
    }

    public function test_whatsapp_channel_excluded_when_globally_disabled(): void
    {
        $gym = Gym::factory()->create();

        GymSetting::query()->create([
            'gym_id' => $gym->id,
            'key' => 'whatsapp_notifications',
            'value' => false,
            'type' => 'boolean',
        ]);

        $this->createConfiguredWhatsAppAccount($gym);

        $channels = NotificationChannelResolver::getChannelsForGym($gym, 'general');

        $this->assertNotContains(WhatsAppChannel::class, $channels);
    }

    public function test_lazy_loading_violation_is_not_raised_when_relation_unloaded(): void
    {
        $gym = Gym::factory()->create();
        $this->createConfiguredWhatsAppAccount($gym);

        $this->expectNotToPerformAssertions();

        // Without loadMissing this would throw LazyLoadingViolationException.
        try {
            NotificationChannelResolver::isWhatsAppConfigured($gym);
        } catch (LazyLoadingViolationException $e) {
            $this->fail('Lazy loading violation was raised: ' . $e->getMessage());
        }
    }
}
