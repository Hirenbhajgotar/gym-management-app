<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\BiometricDataDeletionService;
use App\Biometrics\Services\OfflineSyncService;
use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiometricDeprovisioningAndLeaseTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Gym $gym;

    protected BiometricDevice $admsDevice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->gym = Gym::factory()
            ->hasAttached($this->user, ['role' => DefaultRole::GYM_OWNER->value], 'users')
            ->create();

        $this->admsDevice = BiometricDevice::factory()
            ->admsEnabled('TEST-SN-DEP-01')
            ->withToken('test-secret-token')
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'purpose' => BiometricDevicePurpose::AttendanceOnly,
                'adms_comm_key_hash' => hash('sha256', 'secret-comm-key'),
            ]);
    }

    public function test_member_biometric_deletion_queues_hardware_delete_with_template_ref(): void
    {
        $member = GymMember::factory()->create(['gym_id' => $this->gym->id]);

        BiometricConsent::query()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member->id,
            'version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consented_at' => now(),
        ]);

        $template = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member->id,
            'enrolled_by_device_id' => $this->admsDevice->id,
            'provider_type' => 'adms',
            'template_ref' => '999988',
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
        ]);

        /** @var BiometricDataDeletionService $deletionService */
        $deletionService = resolve(BiometricDataDeletionService::class);
        $deletionService->deleteForGymMember($member);

        $template->refresh();
        $this->assertFalse($template->is_active);
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->sync_status);

        $command = AdmsDeviceCommand::query()
            ->where('device_id', $this->admsDevice->id)
            ->where('command_type', 'user_delete')
            ->first();

        $this->assertNotNull($command);
        $this->assertSame('999988', $command->command_payload['Pin'] ?? null);
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $command->status);
    }

    public function test_terminal_ack_transitions_template_to_deleted(): void
    {
        $member = GymMember::factory()->create(['gym_id' => $this->gym->id]);

        $template = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member->id,
            'enrolled_by_device_id' => $this->admsDevice->id,
            'provider_type' => 'adms',
            'template_ref' => '777123',
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
        ]);

        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->admsDevice->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => '777123'],
            'status' => AdmsCommandStatusEnum::SENT,
        ]);

        $command->markAcknowledged('0');

        $this->assertSame(AdmsCommandStatusEnum::ACKNOWLEDGED, $command->status);
        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $template->sync_status);
    }

    public function test_api_template_delete_routes_through_hardware_deletion(): void
    {
        $member = GymMember::factory()->create(['gym_id' => $this->gym->id]);

        $template = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member->id,
            'enrolled_by_device_id' => $this->admsDevice->id,
            'provider_type' => 'device_native',
            'template_ref' => '555444',
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-secret-token',
        ])->deleteJson(route('api.biometric.template.delete', ['template' => $template->id]));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $template->refresh();
        $this->assertFalse($template->is_active);
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->sync_status);

        $this->assertDatabaseHas('adms_device_commands', [
            'device_id' => $this->admsDevice->id,
            'command_type' => 'user_delete',
        ]);
    }

    public function test_offline_sync_bypasses_attendance_for_door_access_only_devices(): void
    {
        $doorOnlyDevice = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'purpose' => BiometricDevicePurpose::DoorAccessNoAttendance,
            'status' => BiometricDeviceStatusEnum::Active,
            'provider_type' => 'device_native',
        ]);

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonth(),
            'membership_end' => now()->addMonth(),
            'payment_status' => PaymentStatus::COMPLETED,
        ]);

        BiometricConsent::query()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member->id,
            'version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consented_at' => now(),
        ]);

        /** @var OfflineSyncService $syncService */
        $syncService = resolve(OfflineSyncService::class);

        $results = $syncService->processBatch([
            [
                'offline_id' => 'OFFLINE-EVT-001',
                'gym_member_id' => $member->id,
                'captured_at' => now()->subHours(2)->toIso8601String(),
                'biometric_type' => 'face',
            ],
        ], $doorOnlyDevice);

        $this->assertCount(1, $results);
        $this->assertSame('accepted', $results[0]['status']);
        $this->assertNull($results[0]['attendance_id']);

        $this->assertDatabaseMissing('attendances', [
            'device_id' => $doorOnlyDevice->id,
            'attendanceable_id' => $member->id,
        ]);
    }

    public function test_command_leasing_and_stale_recovery(): void
    {
        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->admsDevice->id,
            'command_type' => 'reboot',
            'command_payload' => [],
            'status' => AdmsCommandStatusEnum::PENDING,
            'attempt_count' => 0,
        ]);

        $leased = AdmsDeviceCommand::leaseNext($this->admsDevice->id, 600);
        $this->assertNotNull($leased);
        $this->assertSame($command->id, $leased->id);
        $this->assertSame(AdmsCommandStatusEnum::SENT, $leased->status);
        $this->assertSame(1, $leased->attempt_count);
        $this->assertNotNull($leased->leased_at);

        // While lease is active, leaseNext does NOT return it
        $next = AdmsDeviceCommand::leaseNext($this->admsDevice->id, 600);
        $this->assertNull($next);

        // Simulate stale lease past 600 seconds with attempt < 3
        $leased->update([
            'leased_at' => now()->subMinutes(11),
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('app:reclaim-stale-device-commands')->assertSuccessful();

        $leased->refresh();
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $leased->status);
        $this->assertNull($leased->leased_at);

        // When attempt_count hits 3, stale reclaim marks it FAILED
        $leased->update([
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 3,
            'leased_at' => now()->subMinutes(15),
            'expires_at' => now()->subMinutes(5),
        ]);

        $this->artisan('app:reclaim-stale-device-commands')->assertSuccessful();

        $leased->refresh();
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $leased->status);
        $this->assertSame('timeout_max_attempts', $leased->device_return_code);
    }

    public function test_delete_single_template_pending_not_delivered_marks_deleted_without_hardware_command(): void
    {
        $member = GymMember::factory()->create(['gym_id' => $this->gym->id]);

        $template = BiometricTemplate::factory()->pending()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member->id,
            'enrolled_by_device_id' => $this->admsDevice->id,
            'provider_type' => 'adms',
            'template_ref' => null,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
        ]);

        /** @var BiometricDataDeletionService $deletionService */
        $deletionService = resolve(BiometricDataDeletionService::class);
        $result = $deletionService->deleteSingleTemplate($template);

        $this->assertTrue($result);
        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $template->sync_status);
        $this->assertFalse($template->is_active);

        // Hardware command was NOT emitted because credential was never acknowledged by hardware
        $this->assertDatabaseMissing('adms_device_commands', [
            'device_id' => $this->admsDevice->id,
            'command_type' => 'user_delete',
        ]);
    }

    public function test_delete_single_template_pending_delivered_emits_hardware_user_delete(): void
    {
        $member = GymMember::factory()->create(['gym_id' => $this->gym->id]);

        $template = BiometricTemplate::factory()->pending()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member->id,
            'enrolled_by_device_id' => $this->admsDevice->id,
            'provider_type' => 'adms',
            'template_ref' => null,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
        ]);

        // Prior user_add for this template's own enrolment was ACKNOWLEDGED on terminal
        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->admsDevice->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $member->id, 'Name' => $member->name],
            'status' => AdmsCommandStatusEnum::ACKNOWLEDGED,
            'idempotency_key' => AdmsCommandKey::buildUserAdd($this->gym->id, $this->admsDevice->id, $member->id, $template->id),
        ]);

        /** @var BiometricDataDeletionService $deletionService */
        $deletionService = resolve(BiometricDataDeletionService::class);
        $result = $deletionService->deleteSingleTemplate($template);

        $this->assertTrue($result);
        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->sync_status);
        $this->assertFalse($template->is_active);

        // Hardware user_delete command was queued (GDPR Art. 17)
        $this->assertDatabaseHas('adms_device_commands', [
            'device_id' => $this->admsDevice->id,
            'command_type' => 'user_delete',
            'command_payload->Pin' => (string) $member->id,
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);
    }
}
