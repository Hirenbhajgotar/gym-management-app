<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Testing\TestResponse;
use App\Biometrics\Services\RedisBiometricCacheService;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BiometricScalabilityTest extends TestCase
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

        $this->plainToken = 'scale-test-token-abcdef123456';
        $this->device = BiometricDevice::factory()
            ->withToken($this->plainToken)
            ->withWebhookSecret(self::WEBHOOK_SECRET)
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'provider_type' => null,
                'purpose' => BiometricDevicePurpose::DoorAccess,
            ]);
    }

    public function test_indexed_token_hash_lookup_and_redis_caching(): void
    {
        $hash = hash('sha256', $this->plainToken);
        $cacheKey = 'biometric:device:token:' . $hash;

        Cache::forget($cacheKey);

        $found = BiometricDevice::findByPlainToken($this->plainToken);
        $this->assertNotNull($found);
        $this->assertEquals($this->device->id, $found->id);
        $this->assertEquals($hash, $found->token_hash);

        // Verify device is cached for O(1) in-memory lookup
        $this->assertTrue(Cache::has($cacheKey));

        // Re-read succeeds via memory cache
        $cachedLookup = BiometricDevice::findByPlainToken($this->plainToken);
        $this->assertNotNull($cachedLookup);
        $this->assertEquals($this->device->id, $cachedLookup->id);
    }

    public function test_legacy_device_fallback_and_transparent_token_hash_backfill(): void
    {
        $legacyPlainToken = 'legacy-unhashed-token-xyz789';
        $legacyDevice = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'device_token' => Hash::make($legacyPlainToken),
            'token_hash' => null,
        ]);

        $this->assertNull($legacyDevice->token_hash);

        // Lookup should hit bcrypt fallback and automatically update token_hash in DB
        $found = BiometricDevice::findByPlainToken($legacyPlainToken);

        $this->assertNotNull($found);
        $this->assertEquals($legacyDevice->id, $found->id);

        $legacyDevice->refresh();
        $expectedHash = hash('sha256', $legacyPlainToken);
        $this->assertEquals($expectedHash, $legacyDevice->token_hash);
    }

    public function test_fast_path_member_access_cache_and_invalidation(): void
    {
        $cacheService = resolve(RedisBiometricCacheService::class);

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

        $summary = $cacheService->getMemberAccessSummary($this->gym->id, $member->id);

        $this->assertNotNull($summary);
        $this->assertTrue($summary['can_access']);
        $this->assertNull($summary['denial_reason']);
        $this->assertEquals($this->gym->id, $summary['gym_id']);

        // Member gets frozen -> Observer invalidates cache
        $member->update(['is_frozen' => true]);

        $updatedSummary = $cacheService->getMemberAccessSummary($this->gym->id, $member->id);
        $this->assertNotNull($updatedSummary);
        $this->assertFalse($updatedSummary['can_access']);
        $this->assertEquals('membership_frozen', $updatedSummary['denial_reason']);
    }

    public function test_anti_passback_window_enforces_120_seconds_rejection(): void
    {
        $cacheService = resolve(RedisBiometricCacheService::class);
        $memberId = 99999;

        // First punch acquires anti-passback lock
        $firstAttempt = $cacheService->acquireAntiPassbackLock($this->gym->id, $memberId, 120);
        $this->assertTrue($firstAttempt);

        // Immediate secondary punch within 120 seconds fails
        $secondAttempt = $cacheService->acquireAntiPassbackLock($this->gym->id, $memberId, 120);
        $this->assertFalse($secondAttempt);

        // Via Webhook endpoint
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

        // First punch succeeds
        $firstResponse = $this->postSignedWebhook("/api/v1/biometric/webhook/{$this->plainToken}", [
            'member_id' => $member->id,
        ]);
        $firstResponse->assertOk()->assertJson(['granted' => true]);

        // Rapid duplicate punch fails with anti-passback rejection
        $duplicateResponse = $this->postSignedWebhook("/api/v1/biometric/webhook/{$this->plainToken}", [
            'member_id' => $member->id,
        ]);
        $duplicateResponse->assertStatus(429)
            ->assertJson([
                'granted' => false,
                'reason' => 'anti_passback_rejection',
                'door' => ['action' => 'keep_locked'],
            ]);
    }

    public function test_offline_sync_older_than_24_hours_requires_manager_review(): void
    {
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

        $recentEvent = [
            'offline_id' => 'evt-recent-101',
            'gym_member_id' => $member->id,
            'captured_at' => now()->subHours(3)->toIso8601String(),
            'biometric_type' => 'face',
        ];

        $oldEvent = [
            'offline_id' => 'evt-stale-202',
            'gym_member_id' => $member->id,
            'captured_at' => now()->subHours(26)->toIso8601String(), // > 24 hours
            'biometric_type' => 'face',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->plainToken,
        ])->postJson('/api/v1/biometric/offline-sync', [
            'events' => [$recentEvent, $oldEvent],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'total_events' => 2,
            ]);

        // Recent event granted normally
        $this->assertDatabaseHas('attendances', [
            'device_id' => $this->device->id,
            'offline_id' => 'evt-recent-101',
            'check_in_result' => 'granted',
        ]);

        // Stale event older than 24h flagged for manager review
        $this->assertDatabaseHas('attendances', [
            'device_id' => $this->device->id,
            'offline_id' => 'evt-stale-202',
            'check_in_result' => 'pending_review',
        ]);
    }

    public function test_deadman_switch_marks_unresponsive_devices_inactive(): void
    {
        $healthyDevice = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'last_heartbeat_at' => now()->subMinutes(3),
        ]);

        $unresponsiveDevice = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'last_heartbeat_at' => now()->subMinutes(25), // Past 15m threshold
        ]);

        Artisan::call('biometrics:check-health', ['--timeout' => 15]);

        $healthyDevice->refresh();
        $unresponsiveDevice->refresh();

        $this->assertEquals(BiometricDeviceStatusEnum::Active, $healthyDevice->status);
        $this->assertEquals(BiometricDeviceStatusEnum::Inactive, $unresponsiveDevice->status);

        // Sending heartbeat restores status to Active
        $token = 'reactivate-token-456';
        $unresponsiveDevice->update([
            'device_token' => Hash::make($token),
            'token_hash' => hash('sha256', $token),
        ]);

        $heartbeatResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/biometric/heartbeat');

        $heartbeatResponse->assertOk()->assertJson(['success' => true, 'status' => 'active']);

        $unresponsiveDevice->refresh();
        $this->assertEquals(BiometricDeviceStatusEnum::Active, $unresponsiveDevice->status);
    }

    public function test_newly_registered_device_without_heartbeat_is_not_prematurely_marked_inactive(): void
    {
        $newlyCreatedDevice = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'last_heartbeat_at' => null,
            'created_at' => now()->subMinutes(2), // Just added 2 mins ago (within 15m grace)
        ]);

        $orphanedStaleDevice = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'last_heartbeat_at' => null,
            'created_at' => now()->subMinutes(30), // Created 30 mins ago, never connected
        ]);

        Artisan::call('biometrics:check-health', ['--timeout' => 15]);

        $newlyCreatedDevice->refresh();
        $orphanedStaleDevice->refresh();

        $this->assertEquals(BiometricDeviceStatusEnum::Active, $newlyCreatedDevice->status);
        $this->assertEquals(BiometricDeviceStatusEnum::Inactive, $orphanedStaleDevice->status);
    }

    public function test_non_numeric_member_identifier_handled_safely_in_cache_service(): void
    {
        $cacheService = resolve(RedisBiometricCacheService::class);

        $result = $cacheService->getMemberAccessSummary($this->gym->id, 'CARD-ALPHANUMERIC-99');

        $this->assertNull($result);
    }

    /**
     * Post a webhook payload signed the way a provisioned device signs it.
     *
     * The controller refuses a webhook for a device with no webhook_secret
     * configured, so every webhook in this suite has to be signed.
     *
     * @param  array<string, mixed>  $payload
     */
    private function postSignedWebhook(string $uri, array $payload): TestResponse
    {
        return $this->postJson($uri, $payload, [
            'X-Signature' => 'sha256=' . hash_hmac('sha256', (string) json_encode($payload), self::WEBHOOK_SECRET),
        ]);
    }
}
