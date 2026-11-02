<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages\ListBiometricDevices;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Filament's DeleteBulkAction authorises with deleteAny() only, which carries no
 * tenant predicate. These tests pin the per-record policy check that closes that
 * gap, because the tenant-scoped table query alone is not an authorization
 * boundary for records that reach the action.
 */
class BiometricDeviceBulkDeleteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gymA;

    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = User::factory()->create();
        $this->gymA = Gym::factory()->create();
        $this->gymA->users()->attach($this->operator->id, ['role' => DefaultRole::GYM_OWNER->value]);

        setPermissionsTeamId($this->gymA->id);
        $role = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');
        $this->operator->assignRole($role);

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

        $this->actingAs($this->operator);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_bulk_delete_refuses_a_device_from_another_tenant_even_when_delete_any_is_granted(): void
    {
        $gymB = Gym::factory()->create();
        $foreignDevice = BiometricDevice::factory()->create([
            'gym_id' => $gymB->id,
            'created_by' => $this->operator->id,
            'device_name' => 'Gym B Turnstile',
            'device_serial' => 'SN-GYM-B-BULK',
        ]);

        Filament::setTenant($gymB);

        // Setting the tenant switches the Spatie team to gym B. Restore the
        // actor's own team so the permission-only deleteAny() gate still passes:
        // the per-record policy is the only thing that can refuse this deletion.
        setPermissionsTeamId($this->gymA->id);

        Livewire::test(ListBiometricDevices::class)
            ->callTableBulkAction('delete', [$foreignDevice])
            ->assertNotified('1 terminal(s) refused')
            ->assertNotNotified('Deleted');

        $this->assertDatabaseHas('biometric_devices', [
            'id' => $foreignDevice->id,
            'gym_id' => $gymB->id,
        ]);
    }

    /**
     * The bulk action used to call `success()` unconditionally, so a run in which every
     * record failed still showed a green "Deleted" toast next to the red one. A failed
     * record also has to be counted rather than silently dropped.
     */
    public function test_bulk_delete_reports_a_failed_record_without_claiming_success(): void
    {
        Filament::setTenant($this->gymA);

        $device = $this->makeDevice('SN-VETOED-BULK');

        $shouldVeto = true;

        BiometricDevice::deleting(function () use (&$shouldVeto): bool {
            return ! $shouldVeto;
        });

        try {
            Livewire::test(ListBiometricDevices::class)
                ->callTableBulkAction('delete', [$device])
                ->assertNotified('1 terminal(s) failed')
                ->assertNotNotified('Deleted');
        } finally {
            $shouldVeto = false;
        }

        $this->assertDatabaseHas('biometric_devices', ['id' => $device->id]);
    }

    public function test_bulk_delete_still_deletes_devices_in_the_operators_own_tenant(): void
    {
        Filament::setTenant($this->gymA);

        $deviceA = $this->makeDevice('SN-GYM-A-BULK-1');
        $deviceB = $this->makeDevice('SN-GYM-A-BULK-2');

        Livewire::test(ListBiometricDevices::class)
            ->callTableBulkAction('delete', [$deviceA, $deviceB])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('biometric_devices', ['id' => $deviceA->id]);
        $this->assertDatabaseMissing('biometric_devices', ['id' => $deviceB->id]);
    }

    private function makeDevice(string $serial): BiometricDevice
    {
        return BiometricDevice::factory()->create([
            'gym_id' => $this->gymA->id,
            'created_by' => $this->operator->id,
            'device_serial' => $serial,
        ]);
    }
}
