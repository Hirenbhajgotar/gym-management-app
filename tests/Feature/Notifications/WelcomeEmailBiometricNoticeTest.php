<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\BiometricDeviceStatusEnum;
use App\Jobs\SendBiometricConsentNotificationJob;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\GymMember;
use App\Notifications\Member\WelcomeNotification;
use App\Services\Biometric\BiometricConsentUrlFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WelcomeEmailBiometricNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_notification_includes_biometric_notice_and_link_when_gym_has_active_device(): void
    {
        $gym = Gym::factory()->create(['name' => 'Iron Fitness']);

        BiometricDevice::factory()->create([
            'gym_id' => $gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
        ]);

        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'name' => 'Alice Member',
            'email' => 'alice@example.com',
        ]);

        $notification = new WelcomeNotification($member);

        $this->assertTrue($notification->shouldIncludeBiometricNotice());

        $mail = $notification->toMail($member);
        $html = (string) $mail->render();

        $this->assertStringContainsString('Biometric Attendance', $html);
        $this->assertStringContainsString('Submit Biometric Consent', $html);
        $this->assertStringContainsString('consent/biometric', $html);
        $this->assertStringContainsString(BiometricConsentUrlFactory::validityLabel(), $html);
    }

    /**
     * The copy has to follow the configured TTL rather than assert a literal, or
     * changing the window would leave every welcome email claiming the old one.
     */
    public function test_the_welcome_notice_validity_copy_follows_the_configured_ttl(): void
    {
        Config::set('biometrics.consent_url_expiry_days', 21);

        $this->assertSame('21 days', BiometricConsentUrlFactory::validityLabel());

        $gym = Gym::factory()->create(['name' => 'Long Window Gym']);

        BiometricDevice::factory()->create([
            'gym_id' => $gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
        ]);

        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'name' => 'Nina Member',
            'email' => 'nina@example.com',
        ]);

        $html = (string) (new WelcomeNotification($member))->toMail($member)->render();

        $this->assertStringContainsString('21 days', $html);
        $this->assertStringNotContainsString('7 days', $html);
    }

    public function test_welcome_notification_omits_biometric_notice_when_gym_has_no_biometric_devices(): void
    {
        $gym = Gym::factory()->create(['name' => 'Basic Gym']);

        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'name' => 'Bob Member',
            'email' => 'bob@example.com',
        ]);

        $notification = new WelcomeNotification($member);

        $this->assertFalse($notification->shouldIncludeBiometricNotice());

        $mail = $notification->toMail($member);
        $html = (string) $mail->render();

        $this->assertStringNotContainsString('Biometric Attendance', $html);
        $this->assertStringNotContainsString('Submit Biometric Consent', $html);
        $this->assertStringNotContainsString('consent/biometric', $html);
    }

    public function test_welcome_notification_omits_biometric_notice_when_device_is_inactive_or_disabled(): void
    {
        $gym = Gym::factory()->create(['name' => 'Metro Gym']);

        // 1. Device exists but status is Inactive
        $inactiveDevice = BiometricDevice::factory()->create([
            'gym_id' => $gym->id,
            'status' => BiometricDeviceStatusEnum::Inactive,
            'is_enabled' => true,
        ]);

        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'name' => 'Charlie Member',
            'email' => 'charlie@example.com',
        ]);

        $notification = new WelcomeNotification($member);
        $this->assertFalse($notification->shouldIncludeBiometricNotice());

        $html = (string) $notification->toMail($member)->render();
        $this->assertStringNotContainsString('Biometric Attendance', $html);
        $this->assertStringNotContainsString('Submit Biometric Consent', $html);

        // 2. Device status is Active but is_enabled is false
        $inactiveDevice->update([
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => false,
        ]);

        $notificationAfter = new WelcomeNotification($member);
        $this->assertFalse($notificationAfter->shouldIncludeBiometricNotice());

        $htmlAfter = (string) $notificationAfter->toMail($member)->render();
        $this->assertStringNotContainsString('Biometric Attendance', $htmlAfter);
        $this->assertStringNotContainsString('Submit Biometric Consent', $htmlAfter);
    }

    public function test_welcome_notification_omits_biometric_notice_when_member_already_has_active_consent(): void
    {
        $gym = Gym::factory()->create(['name' => 'Gold Gym']);

        BiometricDevice::factory()->create([
            'gym_id' => $gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
        ]);

        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'name' => 'Diana Member',
            'email' => 'diana@example.com',
        ]);

        BiometricConsent::factory()->create([
            'gym_id' => $gym->id,
            'gym_member_id' => $member->id,
            'revoked_at' => null,
        ]);

        $notification = new WelcomeNotification($member);

        $this->assertFalse($notification->shouldIncludeBiometricNotice());

        $mail = $notification->toMail($member);
        $html = (string) $mail->render();

        $this->assertStringNotContainsString('Biometric Attendance', $html);
        $this->assertStringNotContainsString('Submit Biometric Consent', $html);
    }

    public function test_member_creation_queues_the_consent_job_when_the_gym_runs_biometrics(): void
    {
        $gym = Gym::factory()->create(['name' => 'Onboarding Gym']);

        BiometricDevice::factory()->create([
            'gym_id' => $gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
        ]);

        Queue::fake();

        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'name' => 'Hannah Member',
            'email' => 'hannah@example.com',
        ]);

        Queue::assertPushed(SendBiometricConsentNotificationJob::class, 1);
        Queue::assertPushed(SendBiometricConsentNotificationJob::class, function (SendBiometricConsentNotificationJob $job) use ($member): bool {
            return $job->gymMember->id === $member->id
                && $job->sendEmail === true
                && $job->sendWhatsApp === false;
        });
    }

    public function test_member_creation_queues_nothing_when_the_gym_has_no_biometric_devices(): void
    {
        $gym = Gym::factory()->create(['name' => 'Non Biometric Gym']);

        Queue::fake();

        GymMember::factory()->create([
            'gym_id' => $gym->id,
            'name' => 'Ivan Member',
            'email' => 'ivan@example.com',
        ]);

        Queue::assertNotPushed(SendBiometricConsentNotificationJob::class);
    }

    public function test_member_creation_queues_nothing_when_the_gym_device_is_inactive_or_disabled(): void
    {
        $inactiveGym = Gym::factory()->create(['name' => 'Inactive Device Gym']);

        BiometricDevice::factory()->create([
            'gym_id' => $inactiveGym->id,
            'status' => BiometricDeviceStatusEnum::Inactive,
            'is_enabled' => true,
        ]);

        Queue::fake();

        GymMember::factory()->create([
            'gym_id' => $inactiveGym->id,
            'name' => 'Judy Member',
            'email' => 'judy@example.com',
        ]);

        Queue::assertNotPushed(SendBiometricConsentNotificationJob::class);

        $disabledGym = Gym::factory()->create(['name' => 'Disabled Device Gym']);

        BiometricDevice::factory()->create([
            'gym_id' => $disabledGym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => false,
        ]);

        GymMember::factory()->create([
            'gym_id' => $disabledGym->id,
            'name' => 'Karl Member',
            'email' => 'karl@example.com',
        ]);

        Queue::assertNotPushed(SendBiometricConsentNotificationJob::class);
    }

    public function test_member_creation_queues_nothing_when_the_member_has_no_email(): void
    {
        $gym = Gym::factory()->create(['name' => 'No Email Gym']);

        BiometricDevice::factory()->create([
            'gym_id' => $gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
        ]);

        Queue::fake();

        GymMember::factory()->create([
            'gym_id' => $gym->id,
            'name' => 'Lena Member',
            'email' => '',
        ]);

        Queue::assertNotPushed(SendBiometricConsentNotificationJob::class);
    }
}
