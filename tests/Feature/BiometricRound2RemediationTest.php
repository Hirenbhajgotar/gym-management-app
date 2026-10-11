<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\CheckInService;
use App\Enums\AttendanceStatus;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Models\Attendances;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\BiometricEventReceipt;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BiometricRound2RemediationTest extends TestCase
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

        $this->plainToken = 'remediation-test-token-123456';
        $this->device = BiometricDevice::factory()
            ->withToken($this->plainToken)
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'is_enabled' => true,
                'device_serial' => 'SN-TEST-REMED-01',
                'adms_enabled' => true,
                'provider_type' => null,
                'purpose' => BiometricDevicePurpose::DoorAccess,
                'provider_config' => ['pushcommkey' => 'secretkey123'],
            ]);
    }

    public function test_door_access_without_attendance_does_not_throw_type_error(): void
    {
        $this->device->update(['purpose' => BiometricDevicePurpose::DoorAccessNoAttendance]);

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'payment_status' => PaymentStatus::COMPLETED,
            'membership_start' => now()->subMonth(),
            'membership_end' => now()->addMonth(),
            'is_frozen' => false,
        ]);

        BiometricConsent::query()->create([
            'gym_member_id' => $member->id,
            'gym_id' => $this->gym->id,
            'version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consented_at' => now(),
        ]);

        /** @var CheckInService $checkInService */
        $checkInService = resolve(CheckInService::class);

        // Process device-native check-in for a DoorAccessNoAttendance purpose device (shouldCreateAttendance is false)
        $result = $checkInService->processDeviceNativeCheckIn($this->device, (string) $member->id);

        $this->assertTrue($result->granted);
        $this->assertNull($result->attendance);

        // Verify no attendance record was created in database
        $this->assertDatabaseMissing('attendances', [
            'gym_id' => $this->gym->id,
            'attendanceable_id' => $member->id,
        ]);
    }

    public function test_adms_pushcommkey_validation(): void
    {
        // Missing key when required should return 401
        $responseBad = $this->get('/api/iclock/cdata?SN=SN-TEST-REMED-01&pushcommkey=wrongkey');
        $responseBad->assertUnauthorized();

        // Correct key should succeed handshake
        $responseGood = $this->get('/api/iclock/cdata?SN=SN-TEST-REMED-01&pushcommkey=secretkey123');
        $responseGood->assertOk();
    }

    public function test_biometric_event_receipt_deduplicates_adms_punches(): void
    {
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'payment_status' => PaymentStatus::COMPLETED,
            'membership_start' => now()->subMonth(),
            'membership_end' => now()->addMonth(),
            'is_frozen' => false,
        ]);

        BiometricConsent::query()->create([
            'gym_member_id' => $member->id,
            'gym_id' => $this->gym->id,
            'version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consented_at' => now(),
        ]);

        // Post ATTLOG line with dynamic timestamp
        $timestamp = now()->subHours(2)->format('Y-m-d H:i:s');
        $punchLine = "{$member->id}\t{$timestamp}\t0\t1";

        $response1 = $this->call(
            'POST',
            '/api/iclock/cdata?SN=SN-TEST-REMED-01&table=ATTLOG&pushcommkey=secretkey123',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $punchLine
        );
        $response1->assertOk()->assertSee('OK');

        // Check receipt created
        $this->assertDatabaseHas('biometric_event_receipts', [
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'status' => 'processed',
        ]);

        // Post exact same punch line again
        $response2 = $this->call(
            'POST',
            '/api/iclock/cdata?SN=SN-TEST-REMED-01&table=ATTLOG&pushcommkey=secretkey123',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $punchLine
        );

        $response2->assertOk()->assertSee('OK');

        // Only 1 receipt exists for this fingerprint
        $this->assertEquals(1, BiometricEventReceipt::query()->where('device_id', $this->device->id)->count());
    }

    public function test_administratively_disabled_device_cannot_heartbeat_or_authenticate(): void
    {
        $this->device->update([
            'is_enabled' => false,
            'status' => BiometricDeviceStatusEnum::Inactive,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->plainToken,
        ])->postJson('/api/v1/biometric/heartbeat');

        $response->assertForbidden()
            ->assertJson([
                'error' => 'Forbidden',
                'message' => 'Biometric device has been administratively disabled',
            ]);

        $this->device->refresh();
        $this->assertEquals(BiometricDeviceStatusEnum::Inactive, $this->device->status);
    }

    public function test_attendance_active_scope_includes_unreviewed_checkins_without_checkout(): void
    {
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
        ]);

        // Active attendance
        $validActive = Attendances::query()->create([
            'gym_id' => $this->gym->id,
            'attendanceable_id' => $member->id,
            'attendanceable_type' => GymMember::class,
            'check_in_time' => now()->subHour(),
            'status' => AttendanceStatus::Present,
            'check_in_result' => 'granted',
        ]);

        // Pending review attendance (stale offline sync)
        $pendingReview = Attendances::query()->create([
            'gym_id' => $this->gym->id,
            'attendanceable_id' => $member->id,
            'attendanceable_type' => GymMember::class,
            'check_in_time' => now()->subHours(25),
            'status' => AttendanceStatus::Present,
            'check_in_result' => 'pending_review',
        ]);

        $activeRecords = Attendances::query()->active()->get();

        $this->assertTrue($activeRecords->contains('id', $validActive->id));
        $this->assertTrue($activeRecords->contains('id', $pendingReview->id));
    }

    public function test_pin_must_belong_to_device_gym(): void
    {
        $otherGym = Gym::factory()->create();
        $otherMember = GymMember::factory()->create([
            'gym_id' => $otherGym->id,
        ]);

        /** @var CheckInService $checkInService */
        $checkInService = resolve(CheckInService::class);

        // Attempt check-in using other gym member id as PIN
        $result = $checkInService->processDeviceNativeCheckIn($this->device, (string) $otherMember->id);

        $this->assertFalse($result->granted);
        $this->assertEquals('member_not_found', $result->denialReason);
    }

    public function test_offline_sync_requires_data_or_member_identifier(): void
    {
        $syncToken = 'offline-sync-test-token-xyz789';
        BiometricDevice::factory()
            ->withToken($syncToken)
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'is_enabled' => true,
            ]);

        // Event missing data, template_ref, gym_member_id, member_id, user_id
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $syncToken,
        ])->postJson('/api/v1/biometric/offline-sync', [
            'events' => [
                [
                    'offline_id' => 'offline-event-001',
                    'captured_at' => now()->toISOString(),
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['events.0.data']);
    }

    public function test_biometric_template_cache_invalidation_and_negative_caching(): void
    {
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
        ]);

        $jsonRef = json_encode(['external_id' => 'ext-ref-999', 'format' => 'template_v1']);

        $template = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member->id,
            'template_ref' => $jsonRef,
            'is_active' => true,
        ]);

        // Lookup by external_id
        $found = BiometricTemplate::findByTemplateRef($this->gym->id, 'ext-ref-999');
        $this->assertNotNull($found);
        $this->assertEquals($template->id, $found->id);

        // Verify cached
        $cacheKey = 'biometric:template_ref:' . $this->gym->id . ':' . hash('sha256', 'ext-ref-999');
        $this->assertEquals($template->id, Cache::get($cacheKey));

        // Update template ref and verify cache is cleared
        $newJsonRef = json_encode(['external_id' => 'ext-ref-1000', 'format' => 'template_v1']);
        $template->update(['template_ref' => $newJsonRef]);

        $this->assertNull(Cache::get($cacheKey));

        // Negative caching test
        $missing = BiometricTemplate::findByTemplateRef($this->gym->id, 'non-existent-ref');
        $this->assertNull($missing);

        $missingCacheKey = 'biometric:template_ref:' . $this->gym->id . ':' . hash('sha256', 'non-existent-ref');
        $this->assertSame(0, Cache::get($missingCacheKey));
    }
}
