<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\Contracts\BiometricProvider;
use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\DTOs\CheckInResult;
use App\Biometrics\DTOs\DoorControlResult;
use App\Biometrics\DTOs\VerificationResult;
use App\Biometrics\Services\CheckInService;
use App\Biometrics\Services\DoorControlService;
use App\Biometrics\Services\RedisBiometricCacheService;
use App\Enums\AttendanceStatus;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricProvidersEnum;
use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CheckInServiceProcessCheckInTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected GymMember $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->gym = Gym::factory()
            ->hasAttached($this->user, ['role' => DefaultRole::GYM_OWNER->value], 'users')
            ->create();

        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'device_serial' => 'SN-PROCESS-CHECKIN-01',
            'provider_type' => BiometricProvidersEnum::Rekognition,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
        ]);

        $this->member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'payment_status' => PaymentStatus::COMPLETED,
            'membership_start' => now()->subMonth(),
            'membership_end' => now()->addMonth(),
            'is_frozen' => false,
        ]);

        BiometricConsent::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consented_at' => now(),
        ]);

        BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'biometric_type' => 'face',
            'provider_type' => BiometricProvidersEnum::Rekognition,
            'template_ref' => json_encode([
                'provider' => 'rekognition',
                'external_id' => 'ext_123',
                'meta' => [],
            ]),
        ]);
    }

    public function test_process_check_in_succeeds_without_throwing_on_granted_result(): void
    {
        $mockProvider = Mockery::mock(BiometricProvider::class);
        $mockProvider->shouldReceive('getLivenessScore')
            ->once()
            ->andReturn(0.95);
        $mockProvider->shouldReceive('verify')
            ->once()
            ->andReturn(new VerificationResult(
                matched: true,
                confidence: 0.98,
                livenessScore: 0.95,
                providerRef: 'ext_123',
                raw: []
            ));

        $mockRegistry = Mockery::mock(BiometricProviderRegistry::class);
        $mockRegistry->shouldReceive('resolve')
            ->once()
            ->andReturn($mockProvider);

        $this->app->instance(BiometricProviderRegistry::class, $mockRegistry);

        /** @var CheckInService $checkInService */
        $checkInService = resolve(CheckInService::class);

        $sample = new BiometricSample(type: 'face', data: 'test-sample-data');

        $result = $checkInService->processCheckIn(
            sample: $sample,
            device: $this->device
        );

        $this->assertInstanceOf(CheckInResult::class, $result);
        $this->assertTrue($result->granted);
        $this->assertSame('granted', $result->result);
        $this->assertNull($result->denialReason);
        $this->assertNotNull($result->attendance);
        $this->assertDatabaseHas('attendances', [
            'gym_id' => $this->gym->id,
            'attendanceable_id' => $this->member->id,
            'attendanceable_type' => GymMember::class,
            'status' => AttendanceStatus::Present->value,
        ]);
    }

    public function test_process_check_in_releases_lock_on_denied_result(): void
    {
        $mockProvider = Mockery::mock(BiometricProvider::class);
        $mockProvider->shouldReceive('getLivenessScore')
            ->once()
            ->andReturn(0.95);
        $mockProvider->shouldReceive('verify')
            ->once()
            ->andReturn(new VerificationResult(
                matched: true,
                confidence: 0.98,
                livenessScore: 0.95,
                providerRef: 'ext_123',
                raw: []
            ));

        $mockRegistry = Mockery::mock(BiometricProviderRegistry::class);
        $mockRegistry->shouldReceive('resolve')
            ->once()
            ->andReturn($mockProvider);

        $this->app->instance(BiometricProviderRegistry::class, $mockRegistry);

        // Configure device as door access terminal with a failing controller
        $this->device->update([
            'purpose' => BiometricDevicePurpose::DoorAccess,
        ]);

        $mockDoorService = Mockery::mock(DoorControlService::class);
        $mockDoorService->shouldReceive('unlock')
            ->once()
            ->andReturn(DoorControlResult::failed('door_controller_unreachable'));
        $mockDoorService->shouldReceive('logEvent')
            ->once();

        $this->app->instance(DoorControlService::class, $mockDoorService);

        /** @var CheckInService $checkInService */
        $checkInService = resolve(CheckInService::class);

        $sample = new BiometricSample(type: 'face', data: 'test-sample-data');

        $result = $checkInService->processCheckIn(
            sample: $sample,
            device: $this->device
        );

        $this->assertInstanceOf(CheckInResult::class, $result);
        $this->assertFalse($result->granted);
        $this->assertSame('door_controller_unreachable', $result->denialReason);

        // Verify anti-passback lock was acquired during check-in and successfully released upon denial
        /** @var RedisBiometricCacheService $cacheService */
        $cacheService = resolve(RedisBiometricCacheService::class);
        $canAcquire = $cacheService->acquireAntiPassbackLock($this->gym->id, $this->member->id, 60);
        $this->assertTrue($canAcquire);
        $cacheService->releaseAntiPassbackLock($this->gym->id, $this->member->id);
    }
}
