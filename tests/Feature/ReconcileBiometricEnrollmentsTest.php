<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Actions\EnrollBiometricAction;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReconcileBiometricEnrollmentsTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected GymMember $member;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        $gymOwnerRole = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');
        $updatePerm = Permission::query()->firstOrCreate([
            'name' => 'update_gym_member',
            'guard_name' => 'web',
        ]);
        $gymOwnerRole->givePermissionTo($updatePerm);

        // `GymMember::archive()` is itself authorised, so the operator
        // used to seed an archived member must hold the archive permission the
        // panel would require.
        $archivePerm = Permission::query()->firstOrCreate([
            'name' => 'archive_gym_member',
            'guard_name' => 'web',
        ]);
        $gymOwnerRole->givePermissionTo($archivePerm);
        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->owner = User::factory()->create();
        $this->gym->users()->attach($this->owner->id, ['role' => DefaultRole::GYM_OWNER->value]);
        $this->owner->assignRole($gymOwnerRole);
        $this->actingAs($this->owner);

        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'device_type' => 'fingerprint',
        ]);
        $this->member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
        ]);

        BiometricConsent::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
            'revoked_at' => null,
        ]);
    }

    #[Test]
    public function re_initiating_enrollment_cancels_prior_in_flight_command_but_preserves_new_command(): void
    {
        // 1. Create a prior SENT user_add command
        $priorCommand = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => [
                'Pin' => (string) $this->member->id,
                'Name' => $this->member->name,
            ],
            'status' => AdmsCommandStatusEnum::SENT,
            'idempotency_key' => "user_add:{$this->gym->id}:{$this->device->id}:{$this->member->id}:attempt_old",
        ]);

        // 2. Re-initiate enrollment via EnrollBiometricAction::handle()
        EnrollBiometricAction::handle($this->member, [
            'device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
        ]);

        // 3. Prior command must be CANCELLED and its idempotency_key nulled
        $priorCommand->refresh();
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $priorCommand->status);
        $this->assertNull($priorCommand->idempotency_key);

        // 4. A new PENDING command must exist and NOT be cancelled
        $newCommand = AdmsDeviceCommand::query()
            ->where('device_id', $this->device->id)
            ->where('command_type', 'user_add')
            ->where('status', AdmsCommandStatusEnum::PENDING)
            ->where('command_payload->Pin', (string) $this->member->id)
            ->first();

        $this->assertNotNull($newCommand, 'Replacement command must be queued');
        $this->assertNotNull($newCommand->idempotency_key);
        $this->assertNotSame($priorCommand->id, $newCommand->id);
    }

    #[Test]
    public function reconciler_command_queues_user_add_for_synced_template_lacking_active_command(): void
    {
        // Create a Synced + is_active template with no user_add command
        BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
        ]);

        // Verify no command exists yet
        $this->assertSame(0, AdmsDeviceCommand::query()->count());

        // Run reconciler
        Artisan::call('biometrics:reconcile-enrollments', ['--gym' => $this->gym->id]);

        // Assert user_add command was queued
        $command = AdmsDeviceCommand::query()
            ->where('device_id', $this->device->id)
            ->where('command_type', 'user_add')
            ->first();

        $this->assertNotNull($command);
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $command->status);
        $this->assertIsArray($command->command_payload);
        $this->assertSame((string) $this->member->id, $command->command_payload['Pin'] ?? null);
    }

    #[Test]
    public function reconciler_command_dry_run_does_not_queue_command(): void
    {
        BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
        ]);

        Artisan::call('biometrics:reconcile-enrollments', ['--gym' => $this->gym->id, '--dry-run' => true]);

        $this->assertSame(0, AdmsDeviceCommand::query()->count());
    }

    #[Test]
    public function reconciler_skips_template_when_consent_is_missing(): void
    {
        // Revoke consent
        BiometricConsent::query()->where('gym_member_id', $this->member->id)->update(['revoked_at' => now()]);

        BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
        ]);

        Artisan::call('biometrics:reconcile-enrollments', ['--gym' => $this->gym->id]);

        $this->assertSame(0, AdmsDeviceCommand::query()->count());
    }

    #[Test]
    public function reconciler_queues_command_for_propagated_template_lacking_command(): void
    {
        // B1③: Sibling template in Propagated state must be reconciled if missing command
        BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Propagated,
            'enrolled_at' => null,
        ]);

        Artisan::call('biometrics:reconcile-enrollments', ['--gym' => $this->gym->id]);

        $command = AdmsDeviceCommand::query()
            ->where('device_id', $this->device->id)
            ->where('command_type', 'user_add')
            ->first();

        $this->assertNotNull($command);
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $command->status);
    }

    #[Test]
    public function reconciler_skips_when_valid_acknowledged_command_exists(): void
    {
        BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
        ]);

        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id, 'Name' => $this->member->name],
            'status' => AdmsCommandStatusEnum::ACKNOWLEDGED,
            'device_return_code' => '0',
            'acknowledged_at' => now(),
        ]);

        Artisan::call('biometrics:reconcile-enrollments', ['--gym' => $this->gym->id]);

        $this->assertSame(1, AdmsDeviceCommand::query()->count());
    }

    #[Test]
    public function reconciler_requeues_when_latest_command_failed_or_cancelled(): void
    {
        BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
        ]);

        $failedCmd = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id, 'Name' => $this->member->name],
            'status' => AdmsCommandStatusEnum::FAILED,
            'device_return_code' => '-1',
        ]);

        Artisan::call('biometrics:reconcile-enrollments', ['--gym' => $this->gym->id]);

        $commands = AdmsDeviceCommand::query()
            ->where('device_id', $this->device->id)
            ->where('command_type', 'user_add')
            ->orderBy('id')
            ->get();

        // B1: Re-drives the existing FAILED command in place rather than creating duplicate rows
        $this->assertCount(1, $commands);
        $redrivenCommand = $commands->first();
        $this->assertInstanceOf(AdmsDeviceCommand::class, $redrivenCommand);
        $this->assertSame($failedCmd->id, $redrivenCommand->id);
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $redrivenCommand->status);

        // When command is CANCELLED (with no idempotency key), reconciler queues a replacement command
        $redrivenCommand->update(['status' => AdmsCommandStatusEnum::CANCELLED, 'idempotency_key' => null]);
        Artisan::call('biometrics:reconcile-enrollments', ['--gym' => $this->gym->id]);

        $this->assertSame(2, AdmsDeviceCommand::query()->where('device_id', $this->device->id)->where('command_type', 'user_add')->count());
        $latestCommand = AdmsDeviceCommand::query()->where('device_id', $this->device->id)->latest('id')->first();
        $this->assertNotNull($latestCommand);
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $latestCommand->status);
    }

    #[Test]
    public function reconciler_skips_archived_or_expired_members(): void
    {
        $this->assertTrue($this->member->archive($this->owner), 'Precondition: the member must be archived.');

        BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
        ]);

        Artisan::call('biometrics:reconcile-enrollments', ['--gym' => $this->gym->id]);

        $this->assertSame(0, AdmsDeviceCommand::query()->count());
    }

    #[Test]
    public function acknowledging_a_user_add_for_an_archived_member_parks_the_template_instead_of_activating_it(): void
    {
        // The member is archived while the user_add
        // is still in flight, so the hardware ACK is the last thing that could
        // hand the archived member a live credential back. The flag is set
        // directly rather than through the archive service on purpose: this is
        // the reconciler's own defence-in-depth guard, reached precisely when
        // the synchronous revocation could not complete and the template is
        // still Pending.
        $template = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => null,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
            'enrolled_at' => null,
            'hardware_confirmed_at' => null,
        ]);

        $this->member->forceFill(['is_archived' => true, 'archived_at' => now()])->save();

        $command = AdmsDeviceCommand::factory()->sent()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id, 'Name' => $this->member->name],
            'idempotency_key' => AdmsDeviceCommand::idempotencyKeyFor(
                $this->gym->id,
                $this->device->id,
                $this->member->id,
                $template->id
            ),
        ]);

        $this->assertTrue($command->markAcknowledged('0'));

        $template->refresh();

        $this->assertFalse(
            (bool) $template->is_active,
            'A hardware ACK must not hand an archived member a live credential back.'
        );
        $this->assertSame(
            BiometricTemplateSyncStatusEnum::PendingDeletion,
            $template->sync_status,
            'The acknowledged template must be parked for erasure instead of activated.'
        );

        // The park is only half the claim: the reconciler must own the follow-up.
        Artisan::call('biometrics:reconcile-pending-deletions', ['--gym' => $this->gym->id]);

        $this->assertSame(
            1,
            AdmsDeviceCommand::query()
                ->where('command_type', 'user_delete')
                ->where('status', AdmsCommandStatusEnum::PENDING)
                ->count(),
            'The reconciler must queue exactly one pending erase for the parked PIN.'
        );
    }

    #[Test]
    public function acknowledging_a_user_add_for_an_archived_member_on_a_non_adms_device_leaves_no_delete_command(): void
    {
        // Negative control for the test above: with no ADMS transport there is
        // nothing to drive the erase, so the row stays parked and no command is
        // queued — the reconciler must not fabricate one.
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => false,
            'status' => BiometricDeviceStatusEnum::Active,
        ]);

        $template = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => null,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
            'enrolled_at' => null,
            'hardware_confirmed_at' => null,
        ]);

        $this->member->forceFill(['is_archived' => true, 'archived_at' => now()])->save();

        $command = AdmsDeviceCommand::factory()->sent()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id, 'Name' => $this->member->name],
            'idempotency_key' => AdmsDeviceCommand::idempotencyKeyFor(
                $this->gym->id,
                $device->id,
                $this->member->id,
                $template->id
            ),
        ]);

        $this->assertTrue($command->markAcknowledged('0'));

        $this->assertSame(
            BiometricTemplateSyncStatusEnum::PendingDeletion,
            $template->refresh()->sync_status
        );

        Artisan::call('biometrics:reconcile-pending-deletions', ['--gym' => $this->gym->id]);

        $this->assertDatabaseMissing('adms_device_commands', [
            'device_id' => $device->id,
            'command_type' => 'user_delete',
        ]);
        $this->assertSame(
            BiometricTemplateSyncStatusEnum::PendingDeletion,
            $template->refresh()->sync_status
        );
    }

    #[Test]
    public function reconciler_skips_when_pending_user_delete_exists(): void
    {
        BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
        ]);

        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        Artisan::call('biometrics:reconcile-enrollments', ['--gym' => $this->gym->id]);

        // Only the user_delete command should exist, no new user_add
        $this->assertSame(1, AdmsDeviceCommand::query()->count());
        $this->assertSame('user_delete', AdmsDeviceCommand::query()->firstOrFail()->command_type);
    }

    #[Test]
    public function reconciler_rejects_invalid_or_zero_gym_option(): void
    {
        $this->artisan('biometrics:reconcile-enrollments', ['--gym' => '0'])
            ->expectsOutputToContain('--gym must be a positive integer')
            ->assertFailed();

        $this->artisan('biometrics:reconcile-enrollments', ['--gym' => 'abc'])
            ->expectsOutputToContain('--gym must be a positive integer')
            ->assertFailed();
    }

    #[Test]
    public function reconciler_re_queues_attempt_exhausted_sent_command(): void
    {
        BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
        ]);

        // Attempt-exhausted SENT command
        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id, 'Name' => $this->member->name],
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 3,
        ]);

        $this->artisan('biometrics:reconcile-enrollments', ['--gym' => (string) $this->gym->id])
            ->assertSuccessful();

        // B3: A new reconcile command was queued because the prior SENT command was exhausted
        $reconcileCmd = AdmsDeviceCommand::query()
            ->where('device_id', $this->device->id)
            ->where('command_type', 'user_add')
            ->where('idempotency_key', 'like', '%:reconcile%')
            ->first();

        $this->assertNotNull($reconcileCmd);
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $reconcileCmd->status);
    }

    #[Test]
    public function reconciler_skips_dead_lettered_template_after_failed_reconciliation(): void
    {
        $template = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
        ]);

        $idempotencyKey = AdmsDeviceCommand::idempotencyKeyFor(
            $this->device->gym_id,
            $this->device->id,
            $this->member->id,
            $template->id,
            'reconcile'
        );

        // Previous reconcile command failed permanently
        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id, 'Name' => $this->member->name],
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => 3,
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->artisan('biometrics:reconcile-enrollments', ['--gym' => (string) $this->gym->id])
            ->expectsOutputToContain('1 dead-lettered')
            ->assertSuccessful();

        // No new command was created (still only 1 command)
        $this->assertSame(1, AdmsDeviceCommand::query()->count());
    }

    #[Test]
    public function reconciler_dry_run_reports_candidate_commands_honestly(): void
    {
        BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
        ]);

        $this->artisan('biometrics:reconcile-enrollments', ['--gym' => (string) $this->gym->id, '--dry-run' => true])
            ->expectsOutputToContain('candidate command(s) would be queued')
            ->assertSuccessful();

        $this->assertSame(0, AdmsDeviceCommand::query()->count());
    }
}
