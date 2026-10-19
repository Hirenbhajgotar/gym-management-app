<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricProvidersEnum;
use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiometricCheckInTest extends TestCase
{
    use RefreshDatabase;

    /** Secret every webhook-capable device in this suite is provisioned with. */
    private const WEBHOOK_SECRET = 'test-webhook-secret';

    protected User $user;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected string $plainToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->gym = Gym::factory()
            ->hasAttached($this->user, ['role' => DefaultRole::GYM_OWNER->value], 'users')
            ->create();

        $this->plainToken = 'test-device-token-12345678';
        $this->device = BiometricDevice::factory()
            ->withToken($this->plainToken)
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'provider_type' => 'custom',
            ]);
    }

    public function test_biometric_device_can_authenticate(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->plainToken,
        ])->postJson('/api/v1/biometric/heartbeat', [
            'device_id' => $this->device->id,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'last_heartbeat_at',
            ]);
    }

    public function test_biometric_check_in_requires_valid_device(): void
    {
        $response = $this->postJson('/api/v1/biometric/check-in', [
            'biometric_type' => 'face',
            'biometric_data' => base64_encode('fake_face_data'),
            'device_id' => 'invalid-device-id',
        ]);

        $response->assertUnauthorized();
    }

    public function test_biometric_device_can_get_config(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->plainToken,
        ])->getJson('/api/v1/biometric/device/config');

        $response->assertOk()
            ->assertJsonStructure([
                'device_id',
                'device_name',
                'device_type',
                'provider_type',
                'supported_types',
                'liveness_threshold',
                'confidence_threshold',
            ]);
    }

    public function test_device_native_mode_check_in_via_webhook(): void
    {
        $nativeToken = 'native-device-token-123456';
        $nativeDevice = BiometricDevice::factory()
            ->withToken($nativeToken)
            ->withWebhookSecret(self::WEBHOOK_SECRET)
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'provider_type' => null,
                'purpose' => BiometricDevicePurpose::DoorAccess,
            ]);

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonth(),
            'membership_end' => now()->addMonth(),
            'payment_status' => PaymentStatus::COMPLETED,
            'is_frozen' => false,
        ]);

        BiometricConsent::query()->create([
            'gym_member_id' => $member->id,
            'gym_id' => $this->gym->id,
            'version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consented_at' => now(),
        ]);

        $response = $this->postSignedWebhook("/api/v1/biometric/webhook/{$nativeToken}", [
            'member_id' => $member->id,
            'SN' => 'HIK-12345',
        ]);

        $response->assertOk()
            ->assertJson([
                'granted' => true,
                'door' => [
                    'action' => 'unlock',
                ],
            ]);

        $this->assertDatabaseHas('attendances', [
            'gym_id' => $this->gym->id,
            'device_id' => $nativeDevice->id,
            'check_in_result' => 'granted',
            'provider_type' => 'device_native',
        ]);
    }

    public function test_device_native_mode_webhook_denies_expired_membership(): void
    {
        $nativeToken = 'native-device-token-expired';
        BiometricDevice::factory()
            ->withToken($nativeToken)
            ->withWebhookSecret(self::WEBHOOK_SECRET)
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'provider_type' => null,
                'purpose' => BiometricDevicePurpose::DoorAccess,
            ]);

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonths(2),
            'membership_end' => now()->subDays(5), // Expired
            'payment_status' => PaymentStatus::COMPLETED,
            'is_frozen' => false,
        ]);

        BiometricConsent::query()->create([
            'gym_member_id' => $member->id,
            'gym_id' => $this->gym->id,
            'version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consented_at' => now(),
        ]);

        $response = $this->postSignedWebhook("/api/v1/biometric/webhook/{$nativeToken}", [
            'member_id' => $member->id,
        ]);

        $response->assertForbidden()
            ->assertJson([
                'granted' => false,
                'reason' => 'membership_expired',
                'door' => [
                    'action' => 'keep_locked',
                ],
            ]);
    }

    public function test_device_native_mode_check_in_via_rest_api(): void
    {
        $nativeToken = 'native-rest-token-999';
        BiometricDevice::factory()
            ->withToken($nativeToken)
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'provider_type' => null,
                'purpose' => BiometricDevicePurpose::AttendanceOnly,
            ]);

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonth(),
            'membership_end' => now()->addMonth(),
            'payment_status' => PaymentStatus::COMPLETED,
            'is_frozen' => false,
        ]);

        BiometricConsent::query()->create([
            'gym_member_id' => $member->id,
            'gym_id' => $this->gym->id,
            'version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consented_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $nativeToken,
        ])->postJson('/api/v1/biometric/check-in', [
            'gym_member_id' => $member->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'granted' => true,
                'result' => 'granted',
            ]);
    }

    public function test_cloud_device_rejects_identifier_only_payload(): void
    {
        $cloudToken = 'cloud-device-token-123';
        BiometricDevice::factory()
            ->withToken($cloudToken)
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'provider_type' => BiometricProvidersEnum::Rekognition,
                'purpose' => BiometricDevicePurpose::AttendanceOnly,
            ]);

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $cloudToken,
        ])->postJson('/api/v1/biometric/check-in', [
            'gym_member_id' => $member->id,
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'error' => 'Invalid payload format',
            ]);
    }

    public function test_a_device_without_a_webhook_secret_refuses_webhooks(): void
    {
        // $this->device has no webhook_secret configured. The URL token alone is
        // a bearer credential that leaks through logs, proxies and referrers, so
        // an unsigned webhook on a physical-access surface must be refused — not
        // processed on the token alone.
        $response = $this->postJson("/api/v1/biometric/webhook/{$this->plainToken}", [
            'member_id' => 1,
        ]);

        $response->assertUnauthorized()
            ->assertJson(['error' => 'Webhook signature is not configured for this device']);
    }

    public function test_a_configured_device_still_requires_a_signature(): void
    {
        // The control for the refusal above: with a secret present the endpoint
        // is usable, but an UNSIGNED request is still refused for a different,
        // specific reason. That distinguishes "no secret" from "secret but no
        // signature" and proves the first refusal is not the generic fallback.
        $configuredToken = 'configured-device-token-12345';
        BiometricDevice::factory()
            ->withToken($configuredToken)
            ->withWebhookSecret(self::WEBHOOK_SECRET)
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'provider_type' => null,
                'purpose' => BiometricDevicePurpose::DoorAccess,
            ]);

        $response = $this->postJson("/api/v1/biometric/webhook/{$configuredToken}", [
            'member_id' => 1,
        ]);

        $response->assertUnauthorized()
            ->assertJson(['error' => 'Signature required']);
    }

    /**
     * Post a webhook payload signed the way a provisioned device signs it.
     *
     * The controller refuses a webhook for a device with no webhook_secret
     * configured, so every webhook in this suite has to be signed.
     *
     * @param  array<string, mixed>  $payload
     */
    private function postSignedWebhook(string $uri, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson($uri, $payload, [
            'X-Signature' => 'sha256=' . hash_hmac('sha256', (string) json_encode($payload), self::WEBHOOK_SECRET),
        ]);
    }
}
