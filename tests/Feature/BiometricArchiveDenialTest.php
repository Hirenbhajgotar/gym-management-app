<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Testing\TestResponse;
use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\Contracts\BiometricProvider;
use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\DTOs\CheckInResult;
use App\Biometrics\DTOs\VerificationResult;
use App\Biometrics\Services\CheckInService;
use App\Biometrics\Services\MembershipGuard;
use App\Biometrics\Services\RedisBiometricCacheService;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricProvidersEnum;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Api\BiometricWebhookController;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Services\QRAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * An archived member must be denied with `member_archived` on every check-in path, and that
 * reason must survive into the text the device shows.
 *
 * Denial reasons are produced in two places. MembershipGuard::resolveReason() emits
 * `member_archived` and checks archive first, but CheckInService consults the Redis summary
 * before the guard, so on the device-native path a warm cache decides the reason outright.
 * RedisBiometricCacheService::buildSummaryFromModel() previously mirrored the guard's
 * predicates but not its ordering, so the two disagreed purely on cache warmth — archiving
 * a live, paid member told the operator "membership frozen/expired" or nothing at all
 * depending on whether the summary happened to be cached.
 */
class BiometricArchiveDenialTest extends TestCase
{
    use RefreshDatabase;

    /** getDenialMessage()'s catch-all arm: the answer that tells the operator nothing. */
    private const GENERIC_DENIAL_MESSAGE = 'Access denied. Please contact reception.';

    private const EXPIRY_DENIAL_MESSAGE = 'Your membership has expired. Please renew to check in.';

    /** Webhook signature secret every webhook-capable device in this suite is provisioned with. */
    private const WEBHOOK_SECRET = 'test-webhook-secret';

    protected Gym $gym;

    /** Archived, but otherwise indistinguishable from a healthy member. */
    protected GymMember $archivedMember;

    /** The control: the same membership, minus the archive flag. */
    protected GymMember $activeMember;

    protected BiometricDevice $deviceNativeDevice;

    protected BiometricDevice $cloudDevice;

    protected string $plainToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        // Live membership, fully paid, not frozen, consent on file. Every reason except
        // "archived" is therefore satisfied, so the archive flag is the single variable
        // between $this->archivedMember and $this->activeMember.
        $this->archivedMember = GymMember::factory()->archived()->create($this->livePaidAttributes());
        $this->activeMember = GymMember::factory()->create($this->livePaidAttributes());

        $this->grantConsent($this->archivedMember);
        $this->grantConsent($this->activeMember);

        $this->plainToken = 'archive-denial-token-abcdef123456';
        $this->deviceNativeDevice = BiometricDevice::factory()
            ->withToken($this->plainToken)
            ->withWebhookSecret(self::WEBHOOK_SECRET)
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'is_enabled' => true,
                'provider_type' => null,
                'purpose' => BiometricDevicePurpose::AttendanceOnly,
            ]);

        $this->cloudDevice = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'device_serial' => 'SN-ARCHIVE-DENIAL-01',
            'provider_type' => BiometricProvidersEnum::Rekognition,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
        ]);

        // Exactly one active face template, owned by the archived member. processCheckIn()
        // scans every active template in the gym, so a template on the control member too
        // would make which member gets matched depend on iteration order.
        BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->archivedMember->id,
            'biometric_type' => 'face',
            'provider_type' => BiometricProvidersEnum::Rekognition,
            'template_ref' => json_encode([
                'provider' => 'rekognition',
                'external_id' => 'ext_archive_denial',
                'meta' => [],
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function livePaidAttributes(): array
    {
        return [
            'gym_id' => $this->gym->id,
            'payment_status' => PaymentStatus::COMPLETED,
            'membership_start' => now()->subMonth(),
            'membership_end' => now()->addMonth(),
            'is_frozen' => false,
        ];
    }

    private function grantConsent(GymMember $member): void
    {
        BiometricConsent::query()->create([
            'gym_member_id' => $member->id,
            'gym_id' => $this->gym->id,
            'version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consented_at' => now(),
        ]);
    }

    /**
     * Swap the provider registry for one that always matches, so processCheckIn() resolves
     * to the archived member's template without reaching a cloud provider.
     */
    private function mockMatchingCloudProvider(): void
    {
        $mockProvider = Mockery::mock(BiometricProvider::class);
        $mockProvider->shouldReceive('getLivenessScore')
            ->andReturn(0.95);
        $mockProvider->shouldReceive('verify')
            ->andReturn(new VerificationResult(
                matched: true,
                confidence: 0.98,
                livenessScore: 0.95,
                providerRef: 'ext_archive_denial',
                raw: []
            ));

        $mockRegistry = Mockery::mock(BiometricProviderRegistry::class);
        $mockRegistry->shouldReceive('resolve')
            ->andReturn($mockProvider);

        $this->app->instance(BiometricProviderRegistry::class, $mockRegistry);
    }

    /**
     * Delegated to the service rather than rebuilt here, so this test cannot
     * pass against a key the service no longer writes.
     */
    private function accessSummaryCacheKey(GymMember $member): string
    {
        return resolve(RedisBiometricCacheService::class)
            ->memberAccessSummaryKey($this->gym->id, $member->id);
    }

    private function forgetAccessSummary(GymMember $member): void
    {
        Cache::forget($this->accessSummaryCacheKey($member));
    }

    /**
     * Assert the archived member is denied, and for exactly one reason, on every path.
     */
    private function assertDeniedForArchive(CheckInResult $result): void
    {
        $this->assertFalse($result->granted);
        $this->assertSame('member_archived', $result->denialReason);
    }

    public function test_membership_guard_reports_member_archived_for_a_live_and_paid_membership(): void
    {
        /** @var MembershipGuard $guard */
        $guard = resolve(MembershipGuard::class);

        $member = $this->archivedMember->refresh();

        // Precondition: the archive flag is the only thing standing between this member
        // and a granted check-in, so 'member_archived' cannot be coincidence.
        $this->assertTrue($member->membership_end->isFuture());
        $this->assertSame(PaymentStatus::COMPLETED, $member->payment_status);
        $this->assertFalse($member->isFrozen());
        $this->assertTrue($member->hasActiveBiometricConsent());

        $guardResult = $guard->canCheckIn($member);

        $this->assertFalse($guardResult->allowed);
        $this->assertSame('member_archived', $guardResult->reason);
    }

    public function test_membership_guard_allows_an_unarchived_member_with_an_identical_membership(): void
    {
        /** @var MembershipGuard $guard */
        $guard = resolve(MembershipGuard::class);

        $member = $this->activeMember->refresh();

        $this->assertFalse($member->is_archived);
        $this->assertTrue($member->canAccessGym());

        // Proves the archived denial above came from is_archived and not from one of the
        // other predicates in resolveReason() accidentally matching first.
        $this->assertTrue($guard->canCheckIn($member)->allowed);
    }

    public function test_cached_summary_reports_member_archived_on_a_cold_cache(): void
    {
        /** @var RedisBiometricCacheService $cache */
        $cache = resolve(RedisBiometricCacheService::class);

        $this->forgetAccessSummary($this->archivedMember);

        $summary = $cache->getMemberAccessSummary($this->gym->id, $this->archivedMember->id);

        $this->assertNotNull($summary);
        $this->assertFalse($summary['can_access']);
        $this->assertSame('member_archived', $summary['denial_reason']);
    }

    public function test_cached_summary_reports_member_archived_on_a_warm_cache(): void
    {
        /** @var RedisBiometricCacheService $cache */
        $cache = resolve(RedisBiometricCacheService::class);

        $this->forgetAccessSummary($this->archivedMember);

        $cold = $cache->getMemberAccessSummary($this->gym->id, $this->archivedMember->id);
        $this->assertNotNull($cold);
        $this->assertTrue(
            Cache::has($this->accessSummaryCacheKey($this->archivedMember)),
            'The summary was not cached, so the next read is not the warm path this test claims to cover.'
        );

        $warm = $cache->getMemberAccessSummary($this->gym->id, $this->archivedMember->id);

        $this->assertNotNull($warm);
        $this->assertFalse($warm['can_access']);
        $this->assertSame('member_archived', $warm['denial_reason']);
    }

    public function test_cached_summary_never_relabels_an_archived_member_with_another_reason(): void
    {
        // The second read is the interesting one: getMemberAccessSummary() re-checks
        // membership_end in memory on a cache hit and overwrites denial_reason with
        // 'membership_expired'. That rewrite is guarded on can_access still being true, so
        // archive has to clear can_access rather than just label the summary.
        /** @var RedisBiometricCacheService $cache */
        $cache = resolve(RedisBiometricCacheService::class);

        $this->forgetAccessSummary($this->archivedMember);

        $summaries = [
            $cache->getMemberAccessSummary($this->gym->id, $this->archivedMember->id),
            $cache->getMemberAccessSummary($this->gym->id, $this->archivedMember->id),
        ];

        foreach ($summaries as $summary) {
            $this->assertNotNull($summary);
            $this->assertFalse($summary['can_access']);
            $this->assertSame('member_archived', $summary['denial_reason']);
        }
    }

    public function test_device_native_check_in_reports_member_archived_on_a_cold_cache(): void
    {
        /** @var CheckInService $checkInService */
        $checkInService = resolve(CheckInService::class);

        $this->forgetAccessSummary($this->archivedMember);

        $this->assertFalse(
            Cache::has($this->accessSummaryCacheKey($this->archivedMember)),
            'Precondition failed: the summary is still cached, so this is not the cold path it claims to cover.'
        );

        $result = $checkInService->processDeviceNativeCheckIn(
            $this->deviceNativeDevice,
            (string) $this->archivedMember->id
        );

        $this->assertDeniedForArchive($result);
    }

    public function test_device_native_check_in_reports_member_archived_on_a_warm_cache(): void
    {
        // Warm the summary first: the denial below is then decided by the cached copy,
        // not by the guard CheckInService falls through to.
        resolve(RedisBiometricCacheService::class)
            ->getMemberAccessSummary($this->gym->id, $this->archivedMember->id);

        $this->assertTrue(Cache::has($this->accessSummaryCacheKey($this->archivedMember)));

        /** @var CheckInService $checkInService */
        $checkInService = resolve(CheckInService::class);

        $result = $checkInService->processDeviceNativeCheckIn(
            $this->deviceNativeDevice,
            (string) $this->archivedMember->id
        );

        $this->assertDeniedForArchive($result);
    }

    public function test_cloud_check_in_reports_member_archived(): void
    {
        $this->mockMatchingCloudProvider();

        /** @var CheckInService $checkInService */
        $checkInService = resolve(CheckInService::class);

        $result = $checkInService->processCheckIn(
            sample: new BiometricSample(type: 'face', data: 'archive-denial-sample'),
            device: $this->cloudDevice
        );

        $this->assertDeniedForArchive($result);
    }

    public function test_both_check_in_entry_points_agree_with_the_guard(): void
    {
        $this->mockMatchingCloudProvider();

        $this->forgetAccessSummary($this->archivedMember);

        $guardReason = resolve(MembershipGuard::class)
            ->canCheckIn($this->archivedMember->refresh())
            ->reason;

        /** @var CheckInService $checkInService */
        $checkInService = resolve(CheckInService::class);

        $cloudReason = $checkInService->processCheckIn(
            sample: new BiometricSample(type: 'face', data: 'archive-denial-sample'),
            device: $this->cloudDevice
        )->denialReason;

        $deviceNativeColdReason = $checkInService->processDeviceNativeCheckIn(
            $this->deviceNativeDevice,
            (string) $this->archivedMember->id
        )->denialReason;

        $deviceNativeWarmReason = $checkInService->processDeviceNativeCheckIn(
            $this->deviceNativeDevice,
            (string) $this->archivedMember->id
        )->denialReason;

        $this->assertSame('member_archived', $guardReason);
        $this->assertSame($guardReason, $cloudReason);
        $this->assertSame($guardReason, $deviceNativeColdReason);
        $this->assertSame($guardReason, $deviceNativeWarmReason);
    }

    public function test_denied_webhook_returns_an_archive_specific_device_message(): void
    {
        $this->forgetAccessSummary($this->archivedMember);

        $response = $this->postSignedWebhook("/api/v1/biometric/webhook/{$this->plainToken}", [
            'member_id' => $this->archivedMember->id,
        ]);

        $response->assertForbidden()
            ->assertJson([
                'granted' => false,
                'reason' => 'member_archived',
                'door' => ['action' => 'keep_locked'],
            ]);

        $message = (string) $response->json('display.message');

        $this->assertNotSame(self::GENERIC_DENIAL_MESSAGE, $message);
        $this->assertStringContainsString('archived', strtolower($message));
        $this->assertStringContainsString('reception', strtolower($message));
        $this->assertSame('error', $response->json('display.beep_pattern'));
        $this->assertSame('red', $response->json('display.led_color'));
    }

    public function test_denied_webhook_keeps_the_archive_reason_when_the_cache_is_warm(): void
    {
        resolve(RedisBiometricCacheService::class)
            ->getMemberAccessSummary($this->gym->id, $this->archivedMember->id);

        $this->assertTrue(Cache::has($this->accessSummaryCacheKey($this->archivedMember)));

        $response = $this->postSignedWebhook("/api/v1/biometric/webhook/{$this->plainToken}", [
            'member_id' => $this->archivedMember->id,
        ]);

        $response->assertForbidden()
            ->assertJson([
                'granted' => false,
                'reason' => 'member_archived',
            ]);

        $this->assertNotSame(self::GENERIC_DENIAL_MESSAGE, (string) $response->json('display.message'));
    }

    public function test_denial_message_has_an_explicit_member_archived_case(): void
    {
        $message = $this->invokeDenialMessage('member_archived');

        $this->assertNotSame(self::GENERIC_DENIAL_MESSAGE, $message);
        $this->assertStringContainsString('archived', strtolower($message));
        $this->assertStringContainsString('reception', strtolower($message));
    }

    public function test_every_actionable_membership_denial_reason_has_a_device_message(): void
    {
        // 'access_denied' is deliberately excluded: it is the catch-all bucket and its
        // generic message is the intended answer, not a missing case.
        $reasons = [
            'member_archived',
            'no_membership',
            'membership_frozen',
            'membership_expired',
            'payment_pending',
            'no_biometric_consent',
        ];

        foreach ($reasons as $reason) {
            $this->assertNotSame(
                self::GENERIC_DENIAL_MESSAGE,
                $this->invokeDenialMessage($reason),
                "Denial reason '{$reason}' falls through to the generic device message."
            );
        }
    }

    public function test_qr_attendance_reports_the_archive_instead_of_expiry(): void
    {
        $reason = resolve(QRAttendanceService::class)
            ->getAccessDeniedReason($this->archivedMember->refresh());

        $this->assertNotSame(self::EXPIRY_DENIAL_MESSAGE, $reason);
        $this->assertStringContainsString('archived', strtolower($reason));
        $this->assertStringContainsString('reception', strtolower($reason));
    }

    public function test_qr_attendance_reports_the_archive_for_an_archived_and_frozen_member(): void
    {
        $this->archivedMember->update(['is_frozen' => true]);

        $reason = resolve(QRAttendanceService::class)
            ->getAccessDeniedReason($this->archivedMember->refresh());

        // Archive outranks freeze for the same reason it outranks expiry: the operator's
        // next action differs. A frozen archive must not read as "unfreeze me".
        $this->assertStringContainsString('archived', strtolower($reason));
        $this->assertStringNotContainsString('frozen', strtolower($reason));
    }

    public function test_qr_attendance_still_reports_the_freeze_for_a_frozen_member(): void
    {
        $this->activeMember->update(['is_frozen' => true, 'frozen_until' => now()->addWeek()]);

        $reason = resolve(QRAttendanceService::class)
            ->getAccessDeniedReason($this->activeMember->refresh());

        $this->assertStringContainsString('frozen', strtolower($reason));
        $this->assertStringNotContainsString('archived', strtolower($reason));
    }

    public function test_qr_attendance_reports_no_membership_instead_of_expiry_when_none_is_on_record(): void
    {
        // No `membership_end` at all: the guard deliberately splits this from
        // `membership_expired` (reasonMessage(): "No active membership is on record."), and
        // the QR copy must name the same clause the guard actually denied.
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'payment_status' => PaymentStatus::COMPLETED,
            'membership_start' => now()->subMonth(),
            'membership_end' => null,
            'is_frozen' => false,
        ]);

        $reason = resolve(QRAttendanceService::class)
            ->getAccessDeniedReason($member->refresh());

        $this->assertNotSame(self::EXPIRY_DENIAL_MESSAGE, $reason);
        $this->assertStringContainsString('no active membership', strtolower($reason));
        $this->assertStringContainsString('reception', strtolower($reason));
    }

    /**
     * BiometricWebhookController::getDenialMessage() is protected, so drive it through
     * reflection rather than widening its visibility for a test.
     */
    private function invokeDenialMessage(?string $reason): string
    {
        $controller = new BiometricWebhookController(resolve(CheckInService::class));

        $method = new ReflectionMethod($controller, 'getDenialMessage');
        $method->setAccessible(true);

        /** @var string $message */
        $message = $method->invoke($controller, $reason);

        return $message;
    }

    /**
     * Post a webhook payload signed the way a provisioned device signs it.
     *
     * The controller now refuses a webhook for a device with no webhook_secret
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
