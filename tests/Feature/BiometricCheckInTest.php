<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\DefaultRole;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiometricCheckInTest extends TestCase
{
    use RefreshDatabase;

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
            'device_id' => $this->device->device_id,
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
                'offline_capable',
                'supports_multi_modal',
                'liveness_threshold',
                'confidence_threshold',
            ]);
    }
}
