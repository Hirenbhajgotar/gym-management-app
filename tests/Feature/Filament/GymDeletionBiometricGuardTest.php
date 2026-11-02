<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\DefaultRole;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Deleting a gym cascades to `biometric_devices` at the database level, which skips the
 * model `delete()`, the credential tombstone, the erasure obligations and the deletion
 * audit while destroying the access log and the erasure register with them.
 *
 * The per-device path is the audited one, so gym deletion is refused while devices exist.
 */
class GymDeletionBiometricGuardTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        $role = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');
        $permission = \Spatie\Permission\Models\Permission::query()->firstOrCreate([
            'name' => 'delete_gym',
            'guard_name' => 'web',
        ]);
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->owner = User::factory()->create();
        $this->gym->users()->attach($this->owner->id, ['role' => DefaultRole::GYM_OWNER->value]);
        $this->owner->assignRole($role);
        $this->actingAs($this->owner);

        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_policy_refuses_a_gym_that_still_has_terminals(): void
    {
        BiometricDevice::factory()->create(['gym_id' => $this->gym->id]);

        $this->assertFalse(
            $this->owner->can('delete', $this->gym),
            'A gym with terminals must not be deletable through the panel.'
        );
    }

    public function test_the_policy_refuses_a_gym_with_an_outstanding_erasure_obligation(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'created_by' => $this->owner->id,
            'adms_enabled' => true,
        ]);

        $member = \App\Models\GymMember::factory()->create(['gym_id' => $this->gym->id]);

        \App\Models\BiometricTemplate::factory()->deviceNative()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member->id,
            'enrolled_by_device_id' => $device->id,
            'template_ref' => (string) $member->id,
            'sync_status' => \App\Enums\BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        // Retiring the terminal is the audited path, but it parks the credential as an
        // obligation. The policy must refuse the gym until that obligation is discharged.
        $this->assertTrue($device->delete());

        $this->assertFalse(
            $this->owner->can('delete', $this->gym->fresh()),
            'A gym with an outstanding erasure obligation must not be deletable.'
        );
    }

    public function test_the_model_refuses_a_gym_that_still_has_terminals(): void
    {
        BiometricDevice::factory()->create(['gym_id' => $this->gym->id]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('it still has biometric terminals');

        $this->gym->delete();
    }

    public function test_the_gym_becomes_deletable_once_its_terminals_are_retired_individually(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'created_by' => $this->owner->id,
        ]);

        $this->assertTrue($device->delete());

        $this->assertTrue($this->owner->can('delete', $this->gym->fresh()));
        $this->assertTrue($this->gym->fresh()->delete());
    }
}
