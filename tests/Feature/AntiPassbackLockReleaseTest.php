<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\AdmsEventService;
use App\Biometrics\Services\CheckInService;
use App\Biometrics\Services\RedisBiometricCacheService;
use App\Enums\AttendanceStatus;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Events\MemberCheckedIn;
use App\Models\Attendances;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class AntiPassbackLockReleaseTest extends TestCase
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
            'device_serial' => 'SN-APB-TEST-01',
            'adms_enabled' => true,
            'provider_type' => null,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'provider_config' => ['pushcommkey' => 'commkey123'],
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
    }

    public function test_anti_passback_lock_can_be_acquired_and_released(): void
    {
        /** @var RedisBiometricCacheService $cacheService */
        $cacheService = resolve(RedisBiometricCacheService::class);

        // First acquisition succeeds
        $acquired = $cacheService->acquireAntiPassbackLock($this->gym->id, $this->member->id, 60);
        $this->assertTrue($acquired);

        // Immediate second acquisition is rejected (cooldown active)
        $secondAcquired = $cacheService->acquireAntiPassbackLock($this->gym->id, $this->member->id, 60);
        $this->assertFalse($secondAcquired);

        // Release lock
        $released = $cacheService->releaseAntiPassbackLock($this->gym->id, $this->member->id);
        $this->assertTrue($released);

        // Can acquire again after release
        $reacquired = $cacheService->acquireAntiPassbackLock($this->gym->id, $this->member->id, 60);
        $this->assertTrue($reacquired);

        // Cleanup
        $cacheService->releaseAntiPassbackLock($this->gym->id, $this->member->id);
    }

    public function test_checkout_preserves_anti_passback_cooldown_until_ttl_expiry(): void
    {
        /** @var RedisBiometricCacheService $cacheService */
        $cacheService = resolve(RedisBiometricCacheService::class);

        /** @var AdmsEventService $eventService */
        $eventService = resolve(AdmsEventService::class);

        // Arrange: Create active check-in
        Attendances::query()->create([
            'gym_id' => $this->gym->id,
            'attendanceable_id' => $this->member->id,
            'attendanceable_type' => GymMember::class,
            'check_in_time' => now()->subHour(),
            'status' => AttendanceStatus::Present,
            'check_in_result' => 'granted',
            'device_id' => $this->device->id,
        ]);

        // Acquire lock as if from prior check-in with owner fencing token
        $token = null;
        $acquired = $cacheService->acquireAntiPassbackLock($this->gym->id, $this->member->id, 60, $token);
        $this->assertTrue($acquired);
        $this->assertNotNull($token);

        // Act: Process checkout event (status = 1)
        $result = $eventService->processEvent(
            device: $this->device,
            userId: (string) $this->member->id,
            timestamp: now(),
            status: 1,
            verifyMode: 0
        );

        // Assert: Checkout succeeds
        $this->assertTrue($result->granted);
        $this->assertSame('check_out', $result->action);

        // Assert: Anti-passback cooldown remains active (not wiped by un-fenced DEL on checkout),
        // preventing cross-worker race conditions and allowing the 60s Redis TTL to expire naturally.
        $canLockNow = $cacheService->acquireAntiPassbackLock($this->gym->id, $this->member->id, 60);
        $this->assertFalse($canLockNow);

        // Cleanup: Release lock using the owner token and verify re-acquisition succeeds
        $released = $cacheService->releaseAntiPassbackLock($this->gym->id, $this->member->id, $token);
        $this->assertTrue($released);

        $reacquired = $cacheService->acquireAntiPassbackLock($this->gym->id, $this->member->id, 60);
        $this->assertTrue($reacquired);

        $cacheService->releaseAntiPassbackLock($this->gym->id, $this->member->id);
    }

    public function test_anti_passback_lock_fencing_token_prevents_unauthorized_release(): void
    {
        /** @var RedisBiometricCacheService $cacheService */
        $cacheService = resolve(RedisBiometricCacheService::class);

        $token = null;
        $acquired = $cacheService->acquireAntiPassbackLock($this->gym->id, $this->member->id, 60, $token);
        $this->assertTrue($acquired);
        $this->assertNotEmpty($token);

        // Attempt release with wrong token fails
        $releasedWithWrongToken = $cacheService->releaseAntiPassbackLock(
            $this->gym->id,
            $this->member->id,
            'invalid-owner-token'
        );
        $this->assertFalse($releasedWithWrongToken);

        // Lock is still held
        $this->assertFalse(
            $cacheService->acquireAntiPassbackLock($this->gym->id, $this->member->id, 60)
        );

        // Release with valid token succeeds
        $releasedWithValidToken = $cacheService->releaseAntiPassbackLock(
            $this->gym->id,
            $this->member->id,
            $token
        );
        $this->assertTrue($releasedWithValidToken);

        // Now can acquire again
        $this->assertTrue(
            $cacheService->acquireAntiPassbackLock($this->gym->id, $this->member->id, 60)
        );
        $cacheService->releaseAntiPassbackLock($this->gym->id, $this->member->id);
    }

    public function test_checkin_denial_releases_anti_passback_lock(): void
    {
        /** @var RedisBiometricCacheService $cacheService */
        $cacheService = resolve(RedisBiometricCacheService::class);

        /** @var CheckInService $checkInService */
        $checkInService = resolve(CheckInService::class);

        // Freeze member so check-in is denied
        $this->member->update(['is_frozen' => true]);

        $result = $checkInService->processDeviceNativeCheckIn($this->device, (string) $this->member->id);

        $this->assertFalse($result->granted);
        $this->assertEquals('membership_frozen', $result->denialReason);

        // Lock must have been released upon denial so member isn't locked out of subsequent attempts
        $token = null;
        $canAcquire = $cacheService->acquireAntiPassbackLock($this->gym->id, $this->member->id, 60, $token);
        $this->assertTrue($canAcquire);

        $cacheService->releaseAntiPassbackLock($this->gym->id, $this->member->id, $token);
    }

    public function test_checkin_exception_releases_anti_passback_lock(): void
    {
        /** @var RedisBiometricCacheService $cacheService */
        $cacheService = resolve(RedisBiometricCacheService::class);

        /** @var CheckInService $checkInService */
        $checkInService = resolve(CheckInService::class);

        // Inject failure during check-in finalization via event listener
        Event::listen(MemberCheckedIn::class, function (): void {
            throw new RuntimeException('Simulated check-in failure');
        });

        try {
            $checkInService->processDeviceNativeCheckIn($this->device, (string) $this->member->id);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated check-in failure', $e->getMessage());
        }

        // Assert anti-passback lock was cleared by the catch block
        $token = null;
        $canAcquire = $cacheService->acquireAntiPassbackLock($this->gym->id, $this->member->id, 60, $token);
        $this->assertTrue($canAcquire);

        $cacheService->releaseAntiPassbackLock($this->gym->id, $this->member->id, $token);
    }
}
