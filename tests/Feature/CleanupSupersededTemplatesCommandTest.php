<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CleanupSupersededTemplatesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected GymMember $member;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->member = GymMember::factory()->create(['gym_id' => $this->gym->id]);
        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
        ]);
    }

    public function test_dry_run_previews_without_deleting_superseded_templates(): void
    {
        $oldTemplate = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Superseded,
            'updated_at' => now()->subDays(35),
        ]);

        $this->artisan('biometrics:cleanup-superseded-templates', ['--dry-run' => true])
            ->expectsOutputToContain('Found 1 template(s) to purge (dry-run).')
            ->assertSuccessful();

        $this->assertDatabaseHas('biometric_templates', ['id' => $oldTemplate->id]);
    }

    public function test_purges_superseded_templates_older_than_retention_period(): void
    {
        $oldSuperseded = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Superseded,
            'updated_at' => now()->subDays(35),
        ]);

        $recentSuperseded = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Superseded,
            'updated_at' => now()->subDays(10),
        ]);

        $activeSynced = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'updated_at' => now()->subDays(40),
        ]);

        $this->artisan('biometrics:cleanup-superseded-templates')
            ->expectsOutputToContain('Retired 1 template(s).')
            ->assertSuccessful();

        // Soft deleted: OpenSpec §3 retains Superseded rows for audit, so the record must
        // survive the retention window rather than being destroyed.
        $this->assertSoftDeleted('biometric_templates', ['id' => $oldSuperseded->id]);
        $this->assertDatabaseHas('biometric_templates', ['id' => $recentSuperseded->id]);
        $this->assertDatabaseHas('biometric_templates', ['id' => $activeSynced->id]);
    }

    public function test_rejects_non_positive_days_argument(): void
    {
        $this->artisan('biometrics:cleanup-superseded-templates', ['--days' => 0])
            ->expectsOutputToContain('--days must be an integer greater than 0.')
            ->assertFailed();

        $this->artisan('biometrics:cleanup-superseded-templates', ['--days' => -5])
            ->expectsOutputToContain('--days must be an integer greater than 0.')
            ->assertFailed();
    }

    public function test_rejects_non_positive_pending_days_argument(): void
    {
        $this->artisan('biometrics:cleanup-superseded-templates', ['--pending-days' => 0])
            ->expectsOutputToContain('--pending-days must be an integer greater than 0.')
            ->assertFailed();

        $this->artisan('biometrics:cleanup-superseded-templates', ['--pending-days' => -2])
            ->expectsOutputToContain('--pending-days must be an integer greater than 0.')
            ->assertFailed();
    }

    public function test_purges_abandoned_pending_placeholders_and_cancels_queued_user_add(): void
    {
        $oldPending = BiometricTemplate::factory()->pending()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $pendingAddCmd = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id, 'Name' => $this->member->name],
            'idempotency_key' => "user_add:{$this->gym->id}:{$this->device->id}:{$this->member->id}:{$oldPending->id}",
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        $this->artisan('biometrics:cleanup-superseded-templates')
            ->expectsOutputToContain('Retired 1 template(s).')
            ->assertSuccessful();

        $this->assertSoftDeleted('biometric_templates', ['id' => $oldPending->id]);

        $pendingAddCmd->refresh();
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $pendingAddCmd->status);
    }

    public function test_does_not_purge_propagated_or_synced_templates_even_if_old(): void
    {
        $oldPropagated = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Propagated,
            'template_ref' => 'REF_PIN_123',
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);

        $oldSynced = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'template_ref' => 'REF_PIN_456',
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);

        $this->artisan('biometrics:cleanup-superseded-templates')
            ->expectsOutputToContain('Retired 0 template(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('biometric_templates', ['id' => $oldPropagated->id]);
        $this->assertDatabaseHas('biometric_templates', ['id' => $oldSynced->id]);
    }

    public function test_pending_placeholder_with_acknowledged_user_add_emits_user_delete_on_purge(): void
    {
        $oldPending = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'provider_type' => 'adms',
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
            'template_ref' => null,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        // Acknowledged user_add on hardware, for this placeholder's own enrolment
        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id, 'Name' => $this->member->name],
            'status' => AdmsCommandStatusEnum::ACKNOWLEDGED,
            'idempotency_key' => "user_add:{$this->gym->id}:{$this->device->id}:{$this->member->id}:{$oldPending->id}",
        ]);

        // The row is not retired: its hardware erasure obligation is handed to the
        // de-provisioner, so it must not be reported as retired.
        $this->artisan('biometrics:cleanup-superseded-templates')
            ->expectsOutputToContain('Retired 0 template(s). (0 superseded, 0 undelivered pending)')
            ->expectsOutputToContain('1 pending placeholder(s) were hardware-delivered and remain pending erasure.')
            ->expectsOutputToContain('1 pending placeholder(s) handed to hardware de-provisioning.')
            ->assertSuccessful();

        // Template is transitioned to PendingDeletion (awaiting hardware user_delete confirmation)
        $this->assertDatabaseHas('biometric_templates', [
            'id' => $oldPending->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion->value,
            'is_active' => false,
        ]);

        // user_delete command was queued for the device (GDPR Art. 17)
        $this->assertDatabaseHas('adms_device_commands', [
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload->Pin' => (string) $this->member->id,
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);
    }

    public function test_retires_superseded_templates_without_emitting_a_device_delete_and_retains_the_audit_row(): void
    {
        $oldSuperseded = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Superseded,
            'template_ref' => 'REF_PIN_987',
            'superseded_reason' => BiometricTemplate::SUPERSEDED_BY_ERASURE_OVERRIDE,
            'updated_at' => now()->subDays(35),
        ]);

        $this->artisan('biometrics:cleanup-superseded-templates')
            ->expectsOutputToContain('Retired 1 template(s).')
            ->assertSuccessful();

        // The re-enrolment re-issues a user_add for the same PIN, so the credential is
        // intentionally retained and no user_delete is emitted.
        $this->assertDatabaseMissing('adms_device_commands', [
            'command_type' => 'user_delete',
        ]);

        // The row survives as the audit record of the overridden erasure request, so the
        // override stays queryable and reportable to a regulator.
        $this->assertSoftDeleted('biometric_templates', ['id' => $oldSuperseded->id]);
        $this->assertSame(
            BiometricTemplate::SUPERSEDED_BY_ERASURE_OVERRIDE,
            DB::table('biometric_templates')->where('id', $oldSuperseded->id)->value('superseded_reason')
        );
    }

    public function test_erasure_override_superseded_rows_are_never_hard_deleted_even_after_retention(): void
    {
        $oldOverride = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Superseded,
            'template_ref' => 'REF_PIN_555',
            'superseded_reason' => BiometricTemplate::SUPERSEDED_BY_ERASURE_OVERRIDE,
            'updated_at' => now()->subDays(400),
        ]);

        $this->artisan('biometrics:cleanup-superseded-templates', ['--days' => 1])
            ->assertSuccessful();

        $row = DB::table('biometric_templates')->where('id', $oldOverride->id)->first();

        $this->assertNotNull($row, 'The audit row must never be physically destroyed.');
        $this->assertNotNull($row->deleted_at);
    }

    public function test_pending_placeholder_with_hardware_confirmed_at_and_no_commands_emits_user_delete(): void
    {
        $oldPending = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'provider_type' => 'adms',
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
            'template_ref' => null,
            'hardware_confirmed_at' => now()->subDays(15),
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        // Zero AdmsDeviceCommand rows exist (pruned after retention window)
        $this->assertSame(0, AdmsDeviceCommand::query()->count());

        $this->artisan('biometrics:cleanup-superseded-templates')
            ->expectsOutputToContain('Retired 0 template(s). (0 superseded, 0 undelivered pending)')
            ->expectsOutputToContain('1 pending placeholder(s) were hardware-delivered and remain pending erasure.')
            ->expectsOutputToContain('1 pending placeholder(s) handed to hardware de-provisioning.')
            ->assertSuccessful();

        // Template parked at PendingDeletion awaiting hardware deletion
        $this->assertDatabaseHas('biometric_templates', [
            'id' => $oldPending->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion->value,
            'is_active' => false,
        ]);

        // user_delete command queued on hardware
        $this->assertDatabaseHas('adms_device_commands', [
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload->Pin' => (string) $this->member->id,
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);
    }

    public function test_user_add_ack_stamps_hardware_confirmed_at_and_promotes_pending_placeholder(): void
    {
        $pending = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'provider_type' => 'adms',
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
            'template_ref' => null,
            'enrolled_at' => null,
            'hardware_confirmed_at' => null,
        ]);

        $cmd = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id, 'Name' => $this->member->name],
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 1,
            'idempotency_key' => "user_add:{$this->gym->id}:{$this->device->id}:{$this->member->id}:{$pending->id}",
        ]);

        $cmd->markAcknowledged('0');

        $pending->refresh();
        $this->assertNotNull($pending->hardware_confirmed_at);
        $this->assertNotNull($pending->enrolled_at);
        $this->assertTrue($pending->wasDeliveredToHardware());

        // A hardware acknowledgement is terminal confirmation: the placeholder is promoted
        // to Synced so the 7-day abandoned-placeholder sweep cannot revoke a credential
        // the device is actually holding.
        $this->assertSame(BiometricTemplateSyncStatusEnum::Synced, $pending->sync_status);
        $this->assertTrue($pending->is_active);
    }

    public function test_confirmed_placeholder_is_never_revoked_by_the_pending_sweep(): void
    {
        $confirmed = BiometricTemplate::factory()->synced()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);

        $this->artisan('biometrics:cleanup-superseded-templates')
            ->expectsOutputToContain('Retired 0 template(s).')
            ->assertSuccessful();

        $confirmed->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Synced, $confirmed->sync_status);
        $this->assertTrue($confirmed->is_active);
        $this->assertDatabaseMissing('adms_device_commands', ['command_type' => 'user_delete']);
    }
}
