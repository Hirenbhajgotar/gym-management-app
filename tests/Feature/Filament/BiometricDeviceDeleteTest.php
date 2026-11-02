<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages\ListBiometricDevices;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BiometricDeviceDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected User $gymOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gymOwner = User::factory()->create();
        $this->gym = Gym::factory()->create();
        $this->gym->users()->attach($this->gymOwner->id, ['role' => DefaultRole::GYM_OWNER->value]);

        setPermissionsTeamId($this->gym->id);
        $role = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');
        $this->gymOwner->assignRole($role);

        $viewAnyPermission = Permission::query()->firstOrCreate([
            'name' => 'view_any_biometric_device',
            'guard_name' => 'web',
        ]);
        $deletePermission = Permission::query()->firstOrCreate([
            'name' => 'delete_biometric_device',
            'guard_name' => 'web',
        ]);
        $deleteAnyPermission = Permission::query()->firstOrCreate([
            'name' => 'delete_any_biometric_device',
            'guard_name' => 'web',
        ]);
        $role->permissions()->syncWithoutDetaching([
            $viewAnyPermission->id,
            $deletePermission->id,
            $deleteAnyPermission->id,
        ]);

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->gymOwner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_can_delete_biometric_device_with_enrolled_templates_via_model(): void
    {
        $device = $this->makeDevice('SN-MODEL-DELETE');
        $template = $this->makeEnrolledTemplate($device);

        $this->assertTrue($device->delete());

        $this->assertDatabaseMissing('biometric_devices', ['id' => $device->id]);
        $this->assertTemplateDetachedAndObligated($template);
    }

    public function test_can_delete_biometric_device_with_enrolled_templates_via_filament_table_action(): void
    {
        $device = $this->makeDevice('SN-ROW-ACTION');
        $template = $this->makeEnrolledTemplate($device);

        Livewire::test(ListBiometricDevices::class)
            ->callTableAction('delete', $device)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('biometric_devices', ['id' => $device->id]);
        $this->assertTemplateDetachedAndObligated($template);
    }

    public function test_can_bulk_delete_biometric_devices_with_enrolled_templates(): void
    {
        $device1 = $this->makeDevice('SN-BULK-1');
        $device2 = $this->makeDevice('SN-BULK-2');

        $template1 = $this->makeEnrolledTemplate($device1);
        $template2 = $this->makeEnrolledTemplate($device2);

        Livewire::test(ListBiometricDevices::class)
            ->callTableBulkAction('delete', [$device1, $device2])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('biometric_devices', ['id' => $device1->id]);
        $this->assertDatabaseMissing('biometric_devices', ['id' => $device2->id]);
        $this->assertTemplateDetachedAndObligated($template1);
        $this->assertTemplateDetachedAndObligated($template2);
    }

    /**
     * Relationship isolation: deleting one terminal must not touch another
     * terminal's credentials.
     */
    public function test_deleting_a_device_leaves_another_devices_templates_untouched(): void
    {
        $deleted = $this->makeDevice('SN-DELETED');
        $control = $this->makeDevice('SN-CONTROL');

        $this->makeEnrolledTemplate($deleted);
        $controlTemplate = $this->makeEnrolledTemplate($control);

        $deleted->delete();

        $controlTemplate->refresh();

        $this->assertSame($control->id, $controlTemplate->enrolled_by_device_id);
        $this->assertTrue($controlTemplate->is_active);
        $this->assertSame(BiometricTemplateSyncStatusEnum::Synced, $controlTemplate->sync_status);
        $this->assertFalse((bool) $controlTemplate->is_erasure_obligation);
    }

    /**
     * The command ledger is the only artefact that can later prove a
     * `user_delete` was never sent, so deleting a terminal must not cascade it
     * away.
     */
    public function test_deleting_a_device_keeps_the_adms_command_ledger_and_its_serial_snapshot(): void
    {
        $device = $this->makeDevice('SN-LEDGER');

        $command = AdmsDeviceCommand::factory()->acknowledged()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => '369'],
        ]);

        $device->delete();

        $this->assertDatabaseHas('adms_device_commands', [
            'id' => $command->id,
            'device_id' => null,
            'device_serial' => 'SN-LEDGER',
            'command_type' => 'user_delete',
            'status' => AdmsCommandStatusEnum::ACKNOWLEDGED->value,
        ]);
    }

    /**
     * The ledger, not the null foreign key, decides what may still be in flash.
     *
     * A `Pending` placeholder with no acknowledgement and no `SENT`/`ACKNOWLEDGED`
     * `user_add` provably never reached the terminal and is written off as
     * `Deleted`. Every other live/pending hardware state may be held by the
     * terminal and is parked as an erasure obligation. `Deleted`/`Superseded`
     * rows are already terminal and a cloud row is erased through its provider,
     * so neither is touched.
     */
    #[DataProvider('deleteHookOutcomeProvider')]
    public function test_the_delete_hook_converges_hardware_templates_by_ledger_evidence(
        BiometricTemplateSyncStatusEnum $status,
        string $providerType,
        string $expectedOutcome,
        bool $hardwareConfirmed,
        bool $withSentUserAdd = false,
        bool $withErasureObligation = false,
    ): void {
        $device = $this->makeDevice('SN-HOOK-' . $status->value);
        $template = $this->makeTemplateForDeletion(
            $device,
            $status,
            $providerType,
            $hardwareConfirmed,
            $withErasureObligation,
        );

        if ($withSentUserAdd) {
            $this->recordSentUserAdd($device, $template);
        }

        $this->assertTrue($device->delete());

        $template->refresh();

        $this->assertNull(
            $template->enrolled_by_device_id,
            'The nullOnDelete foreign key still detaches the row from the deleted terminal.'
        );

        if ($expectedOutcome === 'parked') {
            $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable, $template->sync_status);
            $this->assertFalse((bool) $template->is_active);
            $this->assertTrue((bool) $template->is_erasure_obligation);

            return;
        }

        if ($expectedOutcome === 'deleted') {
            $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $template->sync_status);
            $this->assertFalse((bool) $template->is_active);
            $this->assertFalse(
                (bool) $template->is_erasure_obligation,
                'A credential that provably never reached hardware is not an erasure obligation.'
            );

            return;
        }

        $this->assertSame(
            $status,
            $template->sync_status,
            'A row outside the live/pending hardware set must not be rewritten by device deletion.'
        );
        $this->assertTrue((bool) $template->is_active);
        $this->assertFalse((bool) $template->is_erasure_obligation);
    }

    /**
     * @return array<string, array{0: BiometricTemplateSyncStatusEnum, 1: string, 2: string, 3: bool, 4?: bool, 5?: bool}>
     */
    public static function deleteHookOutcomeProvider(): array
    {
        return [
            'pending without delivery evidence is written off' => [
                BiometricTemplateSyncStatusEnum::Pending,
                'device_native',
                'deleted',
                false,
            ],
            'pending with a sent user_add on the ledger is parked' => [
                BiometricTemplateSyncStatusEnum::Pending,
                'device_native',
                'parked',
                false,
                true,
            ],
            'synced is parked' => [
                BiometricTemplateSyncStatusEnum::Synced,
                'device_native',
                'parked',
                true,
            ],
            'propagated is parked' => [
                BiometricTemplateSyncStatusEnum::Propagated,
                'device_native',
                'parked',
                false,
            ],
            'pending deletion is parked' => [
                BiometricTemplateSyncStatusEnum::PendingDeletion,
                'device_native',
                'parked',
                false,
            ],
            'deleted is untouched' => [
                BiometricTemplateSyncStatusEnum::Deleted,
                'device_native',
                'untouched',
                true,
            ],
            'superseded is untouched' => [
                BiometricTemplateSyncStatusEnum::Superseded,
                'device_native',
                'untouched',
                false,
            ],
            'deletion unresolvable without the flag is parked' => [
                BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
                'device_native',
                'parked',
                false,
            ],
            'cloud backed is untouched' => [
                BiometricTemplateSyncStatusEnum::Synced,
                'custom',
                'untouched',
                true,
            ],
        ];
    }

    /**
     * Deletion must be auditable even when it strands no credential: "this
     * terminal held nothing" is the fact an auditor needs.
     */
    public function test_deleting_a_device_without_templates_still_writes_the_audit_entry(): void
    {
        $device = $this->makeDevice('SN-AUDIT-NO-TEMPLATES');
        $deviceId = $device->id;

        $this->assertTrue($device->delete());

        $activity = Activity::query()
            ->where('subject_type', BiometricDevice::class)
            ->where('subject_id', $deviceId)
            ->where('description', 'Biometric terminal deleted')
            ->first();

        $this->assertNotNull($activity, 'The deletion audit is written unconditionally.');

        $properties = $activity->properties;

        $this->assertSame($this->gym->id, (int) $properties['gym_id']);
        $this->assertSame('biometric_device_deleted', $properties['action']);
        $this->assertSame('SN-AUDIT-NO-TEMPLATES', $properties['device_serial']);
        $this->assertSame([], $properties['template_ids']);
        $this->assertSame([], $properties['written_off_template_ids']);
    }

    /**
     * The tombstone saves, the DELETE and the audit share one transaction. A
     * listener registered after the boot hook proves that a failure between the
     * tombstone and the delete rolls the parked template back with the device.
     */
    public function test_a_failure_after_the_tombstone_rolls_back_the_delete_and_the_template(): void
    {
        $device = $this->makeDevice('SN-ROLLBACK');
        $template = $this->makeEnrolledTemplate($device);

        $shouldThrow = true;

        BiometricDevice::deleting(function () use (&$shouldThrow): void {
            if ($shouldThrow) {
                throw new RuntimeException('Injected failure after the tombstone hook.');
            }
        });

        try {
            $device->delete();
            $this->fail('The injected deleting listener must abort the delete.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected failure after the tombstone hook.', $exception->getMessage());
        } finally {
            // The listener cannot be unregistered; disarm it for the rest of the process.
            $shouldThrow = false;
        }

        $this->assertDatabaseHas('biometric_devices', ['id' => $device->id]);

        $template->refresh();

        $this->assertSame($device->id, $template->enrolled_by_device_id);
        $this->assertSame(BiometricTemplateSyncStatusEnum::Synced, $template->sync_status);
        $this->assertTrue((bool) $template->is_active);
        $this->assertFalse((bool) $template->is_erasure_obligation);
    }

    /**
     * The audit is the thing that must not be optional. A listener that throws on the
     * activity insert proves the delete rolls back — and unlike the test above, it proves
     * the failure happens *after* the tombstone and the ledger cancellation, which are the
     * writes a best-effort audit would silently leave committed.
     */
    public function test_an_audit_write_failure_rolls_back_the_delete_tombstone_and_ledger(): void
    {
        $device = $this->makeDevice('SN-AUDIT-FAIL');
        $template = $this->makeEnrolledTemplate($device);

        $pending = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => '777'],
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        $shouldThrow = true;

        Activity::creating(function (Activity $activity) use (&$shouldThrow): void {
            if ($shouldThrow && $activity->description === 'Biometric terminal deleted') {
                throw new RuntimeException('Injected audit sink failure.');
            }
        });

        try {
            $device->delete();
            $this->fail('The injected audit failure must abort the delete.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected audit sink failure.', $exception->getMessage());
        } finally {
            $shouldThrow = false;
        }

        $this->assertDatabaseHas('biometric_devices', ['id' => $device->id]);

        $template->refresh();
        $this->assertSame($device->id, $template->enrolled_by_device_id);
        $this->assertSame(BiometricTemplateSyncStatusEnum::Synced, $template->sync_status);
        $this->assertFalse((bool) $template->is_erasure_obligation);

        $pending->refresh();
        $this->assertSame(
            AdmsCommandStatusEnum::PENDING,
            $pending->status,
            'The ledger cancellation shares the delete transaction and must roll back with it.'
        );
        $this->assertSame($device->id, $pending->device_id);
    }

    /**
     * `ACTIVITY_LOGGER_ENABLED=false` makes the logger return without writing, so the delete
     * would commit unaudited if the transaction were the only guard. Fail closed instead.
     */
    public function test_the_delete_is_refused_when_activity_logging_is_disabled(): void
    {
        $device = $this->makeDevice('SN-AUDIT-DISABLED');
        $template = $this->makeEnrolledTemplate($device);

        config()->set('activitylog.enabled', false);

        try {
            $device->delete();
            $this->fail('A deletion that cannot be audited must not commit.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('activity log is disabled', $exception->getMessage());
        } finally {
            config()->set('activitylog.enabled', true);
        }

        $this->assertDatabaseHas('biometric_devices', ['id' => $device->id]);

        $template->refresh();
        $this->assertSame($device->id, $template->enrolled_by_device_id);
        $this->assertSame(BiometricTemplateSyncStatusEnum::Synced, $template->sync_status);
    }

    /**
     * An audit written on another connection commits outside the delete transaction, so the
     * rollback guarantee the docblock claims does not hold. Refuse rather than pretend.
     */
    public function test_the_delete_is_refused_when_the_audit_uses_another_connection(): void
    {
        $device = $this->makeDevice('SN-AUDIT-CONNECTION');

        config()->set('activitylog.database_connection', 'audit_connection');

        try {
            $device->delete();
            $this->fail('An audit outside the transaction must not be treated as auditable.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('separate database connection', $exception->getMessage());
        } finally {
            config()->set('activitylog.database_connection', null);
        }

        $this->assertDatabaseHas('biometric_devices', ['id' => $device->id]);
    }

    /**
     * A `deleting` listener halts by returning `false`. Eloquent reports that as a normal
     * `false` from `delete()`, after the tombstone has already written — the exact path
     * that committed a park for a terminal that still existed.
     */
    public function test_a_deleting_listener_that_returns_false_rolls_back_and_reports_failure(): void
    {
        $device = $this->makeDevice('SN-VETO');
        $template = $this->makeEnrolledTemplate($device);

        $shouldVeto = true;

        BiometricDevice::deleting(function () use (&$shouldVeto): bool {
            return ! $shouldVeto;
        });

        try {
            $this->assertFalse(
                $device->delete(),
                'A vetoed delete must report failure rather than a successful no-op.'
            );
        } finally {
            $shouldVeto = false;
        }

        $this->assertDatabaseHas('biometric_devices', ['id' => $device->id]);

        $template->refresh();
        $this->assertSame($device->id, $template->enrolled_by_device_id);
        $this->assertSame(BiometricTemplateSyncStatusEnum::Synced, $template->sync_status);
        $this->assertTrue((bool) $template->is_active);
        $this->assertFalse((bool) $template->is_erasure_obligation);
    }

    /**
     * The modal and the hook must agree. The modal used to pre-filter
     * `liveOrPendingCredentialStates()`, so a device whose only credential was already
     * `DeletionUnresolvable` promised "no live enrolled templates" while the hook parked it.
     */
    public function test_the_delete_modal_count_matches_the_templates_the_hook_parks(): void
    {
        $device = $this->makeDevice('SN-MODAL-COUNT');

        $alreadyUnresolvable = $this->makeTemplateForDeletion(
            $device,
            BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            'device_native',
            false,
        );

        $deliveredPending = $this->makeTemplateForDeletion(
            $device,
            BiometricTemplateSyncStatusEnum::Pending,
            'device_native',
            false,
        );
        $this->recordSentUserAdd($device, $deliveredPending);

        $this->assertSame(2, $device->parkedTemplateCount());

        $deviceId = $device->id;
        $device->delete();

        $activity = Activity::query()
            ->where('subject_type', BiometricDevice::class)
            ->where('subject_id', $deviceId)
            ->where('description', 'Biometric terminal deleted')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEqualsCanonicalizing(
            [$alreadyUnresolvable->id, $deliveredPending->id],
            (array) $activity->properties['template_ids']
        );
    }

    public function test_the_policy_refuses_a_device_belonging_to_another_gym(): void
    {
        $device = $this->makeDevice('SN-TENANT');

        $this->assertTrue($this->gymOwner->can('delete', $device));

        $foreignGym = Gym::factory()->create();
        $foreignDevice = BiometricDevice::factory()->create([
            'gym_id' => $foreignGym->id,
            'created_by' => $this->gymOwner->id,
            'device_serial' => 'SN-FOREIGN',
        ]);

        $this->assertFalse(
            $this->gymOwner->can('delete', $foreignDevice),
            'The delete permission for gym A must not authorise deleting a gym B terminal.'
        );
    }

    private function makeDevice(string $serial): BiometricDevice
    {
        return BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'created_by' => $this->gymOwner->id,
            'device_serial' => $serial,
        ]);
    }

    private function makeEnrolledTemplate(BiometricDevice $device): BiometricTemplate
    {
        $member = GymMember::factory()->create(['gym_id' => $this->gym->id]);

        return BiometricTemplate::factory()->deviceNative()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member->id,
            'enrolled_by_device_id' => $device->id,
            'template_ref' => (string) $member->id,
        ]);
    }

    private function makeTemplateForDeletion(
        BiometricDevice $device,
        BiometricTemplateSyncStatusEnum $status,
        string $providerType,
        bool $hardwareConfirmed,
        bool $erasureObligation = false,
    ): BiometricTemplate {
        $member = GymMember::factory()->create(['gym_id' => $this->gym->id]);

        return BiometricTemplate::factory()
            ->state([
                'provider_type' => $providerType,
                'template_ref' => (string) $member->id,
                'is_active' => true,
                'sync_status' => $status,
                'hardware_confirmed_at' => $hardwareConfirmed ? now() : null,
                'is_erasure_obligation' => $erasureObligation,
            ])
            ->create([
                'gym_id' => $this->gym->id,
                'gym_member_id' => $member->id,
                'enrolled_by_device_id' => $device->id,
            ]);
    }

    /**
     * Record a `user_add` the terminal dequeued but never acknowledged. It is the
     * ledger evidence that a `Pending` placeholder may already be in flash.
     */
    private function recordSentUserAdd(BiometricDevice $device, BiometricTemplate $template): AdmsDeviceCommand
    {
        return AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $template->gym_member_id],
            'idempotency_key' => AdmsDeviceCommand::idempotencyKeyFor(
                $this->gym->id,
                $device->id,
                $template->gym_member_id,
                $template->id,
            ),
            'status' => AdmsCommandStatusEnum::SENT,
        ]);
    }

    /**
     * The terminal keeps the credential in flash, so deletion must never read as
     * an erasure. The row is parked for an operator and everything else about it
     * is preserved.
     */
    private function assertTemplateDetachedAndObligated(BiometricTemplate $template): void
    {
        $template->refresh();

        $this->assertNull($template->enrolled_by_device_id);
        $this->assertFalse((bool) $template->is_active);
        $this->assertTrue(
            (bool) $template->is_erasure_obligation,
            'A detached hardware credential must be recorded as an erasure obligation.'
        );
        $this->assertSame(
            BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            $template->sync_status,
            'A null device is not proof of erasure; the row must not converge to Deleted.'
        );
        $this->assertNotSame(BiometricTemplateSyncStatusEnum::Deleted, $template->sync_status);
        $this->assertNotNull($template->template_ref);
        $this->assertNotNull($template->gym_member_id);
        $this->assertNotNull($template->hardware_confirmed_at);
    }
}
