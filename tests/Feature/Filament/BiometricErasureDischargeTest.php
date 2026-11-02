<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\GymMemberResource\Pages\EditGymMember;
use App\Filament\Admin\Resources\GymMemberResource\RelationManagers\BiometricTemplatesRelationManager;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The audited exit from `is_erasure_obligation`.
 *
 * A discharge writes a terminal `Deleted` and clears the only marker that the credential may
 * still be live, so it is only truthful where no automated path can reach the credential
 * (hardware-backed, no bound terminal) and only for MANAGER+. Each negative in the matrix is
 * a shape the action used to accept.
 */
class BiometricErasureDischargeTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected User $owner;

    protected GymMember $member;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        $gymOwnerRole = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->owner = User::factory()->create();
        $this->gym->users()->attach($this->owner->id, ['role' => DefaultRole::GYM_OWNER->value]);
        $this->owner->assignRole($gymOwnerRole);
        $this->actingAs($this->owner);

        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->member = GymMember::factory()->create(['gym_id' => $this->gym->id]);

        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'created_by' => $this->owner->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'adms_enabled' => true,
        ]);
    }

    public function test_confirming_a_physical_erasure_discharges_the_obligation_and_audits_the_actor(): void
    {
        $template = $this->detachedHardwareObligation();
        $ownerId = $this->owner->id;

        $this->relationManager()
            ->assertTableActionVisible('confirm_physical_erasure', $template)
            ->callTableAction('confirm_physical_erasure', $template);

        $template->refresh();

        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $template->sync_status);
        $this->assertFalse((bool) $template->is_active);
        $this->assertFalse(
            (bool) $template->is_erasure_obligation,
            'The operator confirmation must discharge the compliance obligation.'
        );

        $activity = Activity::query()
            ->where('log_name', 'biometric')
            ->where('subject_type', BiometricTemplate::class)
            ->where('subject_id', $template->id)
            ->where('description', 'Biometric erasure confirmed by operator after physical wipe')
            ->first();

        $this->assertNotNull($activity, 'Discharging an erasure obligation must leave an audit entry.');
        $this->assertSame(User::class, $activity->causer_type);
        $this->assertSame($ownerId, (int) $activity->causer_id);
        $this->assertSame($ownerId, (int) $activity->properties['actor_id']);
        $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable->value, $activity->properties['from_status']);
        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted->value, $activity->properties['to_status']);
        $this->assertNotEmpty($activity->properties['confirmed_at']);

        Notification::assertNotified('Physical Erasure Confirmed');
    }

    public function test_a_second_discharge_writes_no_second_audit_entry(): void
    {
        $template = $this->detachedHardwareObligation();

        $this->relationManager()->callTableAction('confirm_physical_erasure', $template);

        // The guarded update is the idempotence boundary: after the flag is cleared the
        // action's own precondition fails, so the second invocation can neither rewrite the
        // row nor append another attestation to the audit trail.
        $this->relationManager()->callTableAction('confirm_physical_erasure', $template);

        $this->assertSame(
            1,
            Activity::query()
                ->where('description', 'Biometric erasure confirmed by operator after physical wipe')
                ->where('subject_id', $template->id)
                ->count(),
            'A repeated discharge must not duplicate the audit attestation.'
        );

        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $template->sync_status);
        $this->assertFalse((bool) $template->is_erasure_obligation);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unauthorisedRoles(): array
    {
        return [
            'receptionist' => [DefaultRole::RECEPTIONIST->value],
            'trainer' => [DefaultRole::TRAINER->value],
        ];
    }

    #[DataProvider('unauthorisedRoles')]
    public function test_the_action_is_refused_for_a_role_below_manager(string $role): void
    {
        $template = $this->detachedHardwareObligation();

        $actorRole = Role::findOrCreate($role, 'web');
        $actor = User::factory()->create();
        $this->gym->users()->attach($actor->id, ['role' => $role]);
        $actor->assignRole($actorRole);
        $this->actingAs($actor);

        $this->relationManager()->assertTableActionHidden('confirm_physical_erasure', $template);

        $template->refresh();

        $this->assertTrue(
            (bool) $template->is_erasure_obligation,
            'An unauthorised caller must not discharge the obligation.'
        );
        $this->assertNotSame(BiometricTemplateSyncStatusEnum::Deleted, $template->sync_status);
    }

    public function test_a_manager_is_authorised_to_discharge(): void
    {
        $managerRole = Role::findOrCreate(DefaultRole::MANAGER->value, 'web');
        $manager = User::factory()->create();
        $this->gym->users()->attach($manager->id, ['role' => DefaultRole::MANAGER->value]);
        $manager->assignRole($managerRole);

        $this->assertTrue(
            $manager->can('dischargeBiometricErasure', $this->member),
            'Discharging an erasure is a MANAGER+ ability.'
        );

        $this->assertFalse(
            $manager->can('dischargeBiometricErasure', GymMember::factory()->create(['gym_id' => Gym::factory()->create()->id])),
            'The per-member tenant check still applies.'
        );
    }

    public function test_the_action_is_hidden_for_a_cloud_backed_obligation(): void
    {
        $template = BiometricTemplate::factory()->cloudBacked()->erasureObligation()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => null,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        $this->relationManager()->assertTableActionHidden('confirm_physical_erasure', $template);

        $this->assertTrue(
            (bool) $template->refresh()->is_erasure_obligation,
            'A cloud credential is erased through its provider, never by an operator attestation.'
        );
    }

    public function test_the_action_is_hidden_for_a_flagged_row_that_still_has_a_terminal(): void
    {
        $template = BiometricTemplate::factory()->deviceNative()->erasureObligation()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            'is_active' => false,
        ]);

        $this->relationManager()->assertTableActionHidden('confirm_physical_erasure', $template);

        $this->assertTrue((bool) $template->refresh()->is_erasure_obligation);
    }

    public function test_retiring_the_terminal_makes_the_discharge_available(): void
    {
        $permission = \Spatie\Permission\Models\Permission::query()->firstOrCreate([
            'name' => 'delete_biometric_device',
            'guard_name' => 'web',
        ]);
        $role = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');
        $role->givePermissionTo($permission);
        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $template = BiometricTemplate::factory()->deviceNative()->erasureObligation()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            'is_active' => false,
        ]);

        $this->relationManager()
            ->assertTableActionVisible('retire_terminal', $template)
            ->callTableAction('retire_terminal', $template);

        $template->refresh();

        $this->assertNull($template->enrolled_by_device_id);
        $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable, $template->sync_status);
        $this->assertTrue((bool) $template->is_erasure_obligation);

        $this->relationManager()->assertTableActionVisible('confirm_physical_erasure', $template);
    }

    public function test_the_discharge_is_refused_when_the_audit_sink_is_disabled(): void
    {
        $template = $this->detachedHardwareObligation();

        config()->set('activitylog.enabled', false);

        try {
            $this->relationManager()->callTableAction('confirm_physical_erasure', $template);
        } finally {
            config()->set('activitylog.enabled', true);
        }

        Notification::assertNotified('Erasure Cannot Be Confirmed');

        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable, $template->sync_status);
        $this->assertTrue(
            (bool) $template->is_erasure_obligation,
            'An erasure that cannot be audited must not be committed.'
        );
    }

    public function test_retry_erasure_is_hidden_for_a_receptionist(): void
    {
        $template = $this->detachedHardwareObligation();

        $receptionistRole = Role::findOrCreate(DefaultRole::RECEPTIONIST->value, 'web');
        $receptionist = User::factory()->create();
        $this->gym->users()->attach($receptionist->id, ['role' => DefaultRole::RECEPTIONIST->value]);
        $receptionist->assignRole($receptionistRole);
        $this->actingAs($receptionist);

        $this->relationManager()->assertTableActionHidden('retry_erasure', $template);
    }

    public function test_the_action_is_hidden_for_an_already_deleted_row(): void
    {
        $template = BiometricTemplate::factory()->deviceNative()->erasureObligation()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => null,
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Deleted,
            'is_active' => false,
        ]);

        $this->relationManager()->assertTableActionHidden('confirm_physical_erasure', $template);
    }

    public function test_the_action_is_hidden_when_the_row_carries_no_erasure_obligation(): void
    {
        $template = BiometricTemplate::factory()->deviceNative()->synced()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'template_ref' => (string) $this->member->id,
        ]);

        $this->relationManager()->assertTableActionHidden('confirm_physical_erasure', $template);
    }

    public function test_a_manager_of_another_tenant_cannot_discharge(): void
    {
        $template = $this->detachedHardwareObligation();

        $foreignGym = Gym::factory()->create();
        $managerRole = Role::findOrCreate(DefaultRole::MANAGER->value, 'web');
        $foreignManager = User::factory()->create();
        $foreignGym->users()->attach($foreignManager->id, ['role' => DefaultRole::MANAGER->value]);
        $foreignManager->assignRole($managerRole);

        $this->actingAs($foreignManager);

        $this->relationManager()->assertTableActionHidden('confirm_physical_erasure', $template);

        $this->assertTrue((bool) $template->refresh()->is_erasure_obligation);
    }

    private function relationManager(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(BiometricTemplatesRelationManager::class, [
            'ownerRecord' => $this->member,
            'pageClass' => EditGymMember::class,
        ]);
    }

    /**
     * The only shape a human may attest: hardware-backed, detached, still flagged.
     */
    private function detachedHardwareObligation(): BiometricTemplate
    {
        return BiometricTemplate::factory()->deviceNative()->erasureObligation()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => null,
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            'is_active' => false,
        ]);
    }
}
