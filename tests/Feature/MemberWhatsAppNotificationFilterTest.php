<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Enums\WhatsAppPhoneNumberStatusEnum;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\GymSetting;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Notifications\BirthdayNotification;
use App\Notifications\WhatsAppChannel;
use App\Services\GymContextResolver;
use App\Services\NotificationChannelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MemberWhatsAppNotificationFilterTest extends TestCase
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
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
        ]);

        return $account;
    }

    public function test_can_receive_whatsapp_notifications_returns_true_only_for_active_and_allowed_member(): void
    {
        $activeAllowedMember = GymMember::factory()->make([
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $archivedMember = GymMember::factory()->make([
            'is_archived' => true,
            'allow_whatsapp_marketing' => true,
        ]);

        $disallowedMember = GymMember::factory()->make([
            'is_archived' => false,
            'allow_whatsapp_marketing' => false,
        ]);

        $archivedDisallowedMember = GymMember::factory()->make([
            'is_archived' => true,
            'allow_whatsapp_marketing' => false,
        ]);

        $this->assertTrue($activeAllowedMember->canReceiveWhatsappNotifications());
        $this->assertFalse($archivedMember->canReceiveWhatsappNotifications());
        $this->assertFalse($disallowedMember->canReceiveWhatsappNotifications());
        $this->assertFalse($archivedDisallowedMember->canReceiveWhatsappNotifications());
    }

    public function test_notification_channel_resolver_excludes_whatsapp_when_member_is_archived_or_disallowed(): void
    {
        $gym = Gym::factory()->create();
        GymSetting::query()->create([
            'gym_id' => $gym->id,
            'key' => 'whatsapp_notifications',
            'value' => true,
            'type' => 'boolean',
        ]);
        $this->createConfiguredWhatsAppAccount($gym);

        $activeAllowedMember = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $archivedMember = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => true,
            'allow_whatsapp_marketing' => true,
        ]);

        $disallowedMember = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => false,
        ]);

        $channelsAllowed = NotificationChannelResolver::getChannelsForGym($gym, 'general', $activeAllowedMember);
        $channelsArchived = NotificationChannelResolver::getChannelsForGym($gym, 'general', $archivedMember);
        $channelsDisallowed = NotificationChannelResolver::getChannelsForGym($gym, 'general', $disallowedMember);

        $this->assertContains(WhatsAppChannel::class, $channelsAllowed);
        $this->assertNotContains(WhatsAppChannel::class, $channelsArchived);
        $this->assertNotContains(WhatsAppChannel::class, $channelsDisallowed);
    }

    public function test_base_tenant_notification_via_excludes_whatsapp_channel_for_archived_or_disallowed_member(): void
    {
        $gym = Gym::factory()->create();
        GymSetting::query()->create([
            'gym_id' => $gym->id,
            'key' => 'whatsapp_notifications',
            'value' => true,
            'type' => 'boolean',
        ]);
        $this->createConfiguredWhatsAppAccount($gym);

        $archivedMember = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => true,
            'allow_whatsapp_marketing' => true,
        ]);

        $notification = new BirthdayNotification($archivedMember);
        $channels = $notification->via($archivedMember);

        $this->assertNotContains(WhatsAppChannel::class, $channels);
    }

    public function test_whatsapp_channel_send_returns_null_and_logs_when_member_is_archived_or_disallowed(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'WhatsApp notification skipped')
                    && isset($context['member_id']);
            });
        Log::shouldReceive('emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'debug', 'log', 'info')->zeroOrMoreTimes();

        $gym = Gym::factory()->create();
        $disallowedMember = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => false,
        ]);

        $resolver = $this->app->make(GymContextResolver::class);
        $channel = new WhatsAppChannel($resolver);
        $notification = new BirthdayNotification($disallowedMember);

        $result = $channel->send($disallowedMember, $notification);

        $this->assertNull($result);
    }

    public function test_birthday_notification_to_whatsapp_returns_null_when_member_is_archived_or_disallowed(): void
    {
        config(['services.twilio.use_sandbox' => true]);

        $gym = Gym::factory()->create();
        $archivedMember = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => true,
            'allow_whatsapp_marketing' => true,
            'phone' => '+14155551234',
        ]);

        $notification = new BirthdayNotification($archivedMember);
        $payload = $notification->toWhatsApp($archivedMember);

        $this->assertNull($payload);
    }

    public function test_email_channel_remains_active_when_whatsapp_is_filtered_for_archived_or_disallowed_member(): void
    {
        $gym = Gym::factory()->create();
        GymSetting::query()->create([
            'gym_id' => $gym->id,
            'key' => 'email_notifications',
            'value' => true,
            'type' => 'boolean',
        ]);
        GymSetting::query()->create([
            'gym_id' => $gym->id,
            'key' => 'whatsapp_notifications',
            'value' => true,
            'type' => 'boolean',
        ]);
        $this->createConfiguredWhatsAppAccount($gym);

        $archivedMember = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => true,
            'allow_whatsapp_marketing' => false,
        ]);

        $notification = new BirthdayNotification($archivedMember);
        $channels = $notification->via($archivedMember);

        // Mail channel MUST still be present
        $this->assertContains('mail', $channels);
        // WhatsApp channel MUST NOT be present
        $this->assertNotContains(WhatsAppChannel::class, $channels);

        // Mail representation should still work normally
        $mail = $notification->toMail($archivedMember);
        $this->assertStringContainsString('Happy Birthday', $mail->subject);
    }
}
