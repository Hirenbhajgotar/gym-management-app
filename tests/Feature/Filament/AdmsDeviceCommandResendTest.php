<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\AdmsCommandStatusEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\AdmsDeviceCommandResource;
use App\Filament\Admin\Resources\AdmsDeviceCommandResource\Pages\ListAdmsDeviceCommands;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdmsDeviceCommandResendTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected User $owner;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        $role = Role::findOrCreate(DefaultRole::SUPER_ADMIN->value, 'web');

        $this->owner = User::factory()->create();
        $this->gym->users()->attach($this->owner->id, ['role' => DefaultRole::GYM_OWNER->value]);
        $this->owner->assignRole($role);

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
        ]);
    }

    /**
     * A `user_add` carries a member's PIN and name, so the resend gate can only clear it when
     * that member exists in the command's gym and holds active consent.
     */
    protected function consentedMember(): GymMember
    {
        $member = GymMember::factory()->create(['gym_id' => $this->gym->id]);

        BiometricConsent::factory()->create([
            'gym_member_id' => $member->id,
            'gym_id' => $this->gym->id,
        ]);

        return $member;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function failedUserAdd(GymMember $member, array $overrides = []): AdmsDeviceCommand
    {
        return AdmsDeviceCommand::query()->create(array_merge([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $member->id, 'Name' => $member->name],
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => 3,
            'device_return_code' => '-1',
        ], $overrides));
    }

    public function test_single_resend_resets_attempt_count_and_enables_leasing(): void
    {
        $this->actingAs($this->owner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $member = $this->consentedMember();

        $command = $this->failedUserAdd($member, [
            'sent_at' => now()->subHours(2),
            'leased_at' => now()->subHours(2),
            'expires_at' => now()->subHours(1),
            'acknowledged_at' => now()->subHours(1),
        ]);

        // Prior to resend, leaseNext must NOT lease an exhausted command (attempt_count >= max)
        $unleased = AdmsDeviceCommand::leaseNext($this->device->id, 600);
        $this->assertNull($unleased);

        Livewire::test(ListAdmsDeviceCommands::class)
            ->callTableAction('resend', $command);

        $command->refresh();
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $command->status);
        $this->assertSame(0, $command->attempt_count);
        $this->assertNull($command->sent_at);
        $this->assertNull($command->leased_at);
        $this->assertNull($command->expires_at);
        $this->assertNull($command->acknowledged_at);
        $this->assertNull($command->device_return_code);

        // After resend, leaseNext can lease the command
        $leased = AdmsDeviceCommand::leaseNext($this->device->id, 600);
        $this->assertNotNull($leased);
        $this->assertSame($command->id, $leased->id);
        $this->assertSame(1, $leased->attempt_count);
        $this->assertSame(AdmsCommandStatusEnum::SENT, $leased->status);
    }

    public function test_bulk_resend_resets_attempt_count_for_selected_commands(): void
    {
        $this->actingAs($this->owner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $cmd1 = $this->failedUserAdd($this->consentedMember(), ['device_return_code' => '-1']);
        $cmd2 = $this->failedUserAdd($this->consentedMember(), ['device_return_code' => '-2']);

        Livewire::test(ListAdmsDeviceCommands::class)
            ->callTableBulkAction('resendSelected', [$cmd1, $cmd2]);

        $cmd1->refresh();
        $cmd2->refresh();

        $this->assertSame(AdmsCommandStatusEnum::PENDING, $cmd1->status);
        $this->assertSame(0, $cmd1->attempt_count);

        $this->assertSame(AdmsCommandStatusEnum::PENDING, $cmd2->status);
        $this->assertSame(0, $cmd2->attempt_count);
    }

    public function test_the_consent_gate_closes_when_consent_is_revoked(): void
    {
        $this->actingAs($this->owner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $member = $this->consentedMember();
        $command = $this->failedUserAdd($member);

        $this->assertTrue($command->isConsentPermitted());

        BiometricConsent::query()
            ->where('gym_member_id', $member->id)
            ->update(['revoked_at' => now()]);

        $this->assertFalse($command->isConsentPermitted());
    }

    public function test_resend_does_not_requeue_a_user_add_for_a_member_without_consent(): void
    {
        $this->actingAs($this->owner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $member = GymMember::factory()->create(['gym_id' => $this->gym->id]);
        $command = $this->failedUserAdd($member);

        $this->assertFalse($command->isConsentPermitted());

        Livewire::test(ListAdmsDeviceCommands::class)
            ->assertTableActionHidden('resend', $command);

        $command->refresh();
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $command->status);
        $this->assertSame(3, $command->attempt_count);
    }

    public function test_resend_is_hidden_when_the_target_member_cannot_be_resolved(): void
    {
        $this->actingAs($this->owner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => '999999', 'Name' => 'Ghost'],
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => 3,
            'device_return_code' => '-1',
        ]);

        // Consent cannot be proven for a member we cannot identify, so the gate stays shut.
        $this->assertNull($command->resolveTargetMember());
        $this->assertFalse($command->isConsentPermitted());

        Livewire::test(ListAdmsDeviceCommands::class)
            ->assertTableActionHidden('resend', $command);
    }

    public function test_bulk_resend_skips_members_without_consent_and_requeues_the_rest(): void
    {
        $this->actingAs($this->owner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $consented = $this->consentedMember();
        $revoked = $this->consentedMember();

        $cmdConsented = $this->failedUserAdd($consented);
        $cmdRevoked = $this->failedUserAdd($revoked);

        BiometricConsent::query()
            ->where('gym_member_id', $revoked->id)
            ->update(['revoked_at' => now()]);

        Livewire::test(ListAdmsDeviceCommands::class)
            ->callTableBulkAction('resendSelected', [$cmdConsented, $cmdRevoked]);

        $freshConsented = $cmdConsented->fresh();
        $this->assertNotNull($freshConsented);
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $freshConsented->status);
        $this->assertSame(0, $freshConsented->attempt_count);

        $freshRevoked = $cmdRevoked->fresh();
        $this->assertNotNull($freshRevoked);
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $freshRevoked->status);
        $this->assertSame(3, $freshRevoked->attempt_count);
    }

    public function test_bulk_resend_does_not_cross_tenants(): void
    {
        $this->actingAs($this->owner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $otherGym = Gym::factory()->create();
        $otherDevice = BiometricDevice::factory()->create([
            'gym_id' => $otherGym->id,
            'adms_enabled' => true,
        ]);

        $otherMember = GymMember::factory()->create(['gym_id' => $otherGym->id]);
        BiometricConsent::factory()->create([
            'gym_member_id' => $otherMember->id,
            'gym_id' => $otherGym->id,
        ]);

        $foreign = AdmsDeviceCommand::query()->create([
            'gym_id' => $otherGym->id,
            'device_id' => $otherDevice->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $otherMember->id],
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => 3,
            'device_return_code' => '-1',
        ]);

        $mine = $this->failedUserAdd($this->consentedMember());

        Livewire::test(ListAdmsDeviceCommands::class)
            ->callTableBulkAction('resendSelected', [$mine, $foreign]);

        $freshMine = $mine->fresh();
        $this->assertNotNull($freshMine);
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $freshMine->status);

        $freshForeign = $foreign->fresh();
        $this->assertNotNull($freshForeign);
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $freshForeign->status);
        $this->assertSame(3, $freshForeign->attempt_count);
    }

    /**
     * The bulk action's tenant guard is unreachable through the table, because Filament
     * re-resolves the selection through the tenant-scoped query. This pins the guard at its
     * single owner so removing it fails here rather than passing silently for seven rounds.
     *
     * The acting user must be tenant-scoped: a super admin is legitimately allowed to resend
     * across gyms, so only a gym owner can demonstrate the refusal.
     */
    public function test_the_resend_guard_refuses_a_command_from_another_tenant(): void
    {
        $gymOwnerRole = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');
        $tenantOwner = User::factory()->create();
        $this->gym->users()->attach($tenantOwner->id, ['role' => DefaultRole::GYM_OWNER->value]);
        $tenantOwner->assignRole($gymOwnerRole);

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($tenantOwner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $otherGym = Gym::factory()->create();
        $otherDevice = BiometricDevice::factory()->create([
            'gym_id' => $otherGym->id,
            'adms_enabled' => true,
        ]);
        $otherMember = GymMember::factory()->create(['gym_id' => $otherGym->id]);

        $foreign = $this->failedUserAdd($otherMember, [
            'gym_id' => $otherGym->id,
            'device_id' => $otherDevice->id,
        ]);
        $mine = $this->failedUserAdd($this->consentedMember());

        $this->assertFalse(
            AdmsDeviceCommandResource::isAuthorizedToResend($foreign),
            'The bulk resend guard must refuse a command belonging to another gym.'
        );
        $this->assertTrue(
            AdmsDeviceCommandResource::isAuthorizedToResend($mine),
            'The guard must not refuse the operator their own gym command.'
        );
        $this->assertFalse(
            AdmsDeviceCommandResource::canResend($foreign),
            'canResend() composes the tenant guard, so a cross-tenant record can never be re-queued.'
        );
    }

    /**
     * `prunable()` deliberately retains dead-lettered rows, so a resend must not destroy the
     * evidence that the cap was reached. The reset is audited with the values it replaced.
     */
    public function test_resend_preserves_the_dead_letter_high_water_mark_and_audits_the_reset(): void
    {
        $this->actingAs($this->owner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $command = $this->failedUserAdd($this->consentedMember(), [
            'attempt_count' => 3,
            'device_return_code' => 'E-DEADLETTER',
            'acknowledged_at' => now()->subDays(200),
        ]);

        Livewire::test(ListAdmsDeviceCommands::class)
            ->callTableAction('resend', $command);

        $command->refresh();

        $this->assertSame(0, $command->attempt_count);
        $this->assertSame(
            3,
            $command->max_attempt_count,
            'The high-water mark must survive the reset or the row becomes prunable evidence.'
        );

        $activity = Activity::query()
            ->where('log_name', 'adms')
            ->where('subject_type', AdmsDeviceCommand::class)
            ->where('subject_id', $command->id)
            ->where('description', 'ADMS command re-queued by operator')
            ->first();

        $this->assertNotNull($activity, 'An operator resend must be auditable.');
        $this->assertSame($this->owner->id, (int) $activity->causer_id);
        $this->assertSame(3, (int) $activity->properties['previous_attempt_count']);
        $this->assertSame('E-DEADLETTER', $activity->properties['previous_device_return_code']);
        $this->assertSame(3, (int) $activity->properties['max_attempt_count']);
    }

    public function test_user_delete_commands_are_not_subject_to_the_consent_gate(): void
    {
        $this->actingAs($this->owner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $member = GymMember::factory()->create(['gym_id' => $this->gym->id]);

        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $member->id],
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => 3,
            'device_return_code' => '-1',
        ]);

        // Erasure must remain possible after consent is gone.
        $this->assertTrue($command->isConsentPermitted());

        Livewire::test(ListAdmsDeviceCommands::class)
            ->callTableAction('resend', $command);

        $freshCommand = $command->fresh();
        $this->assertNotNull($freshCommand);
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $freshCommand->status);
    }
}
