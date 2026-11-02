<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricTypeEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages\CreateBiometricDevice;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages\EditBiometricDevice;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * `provider_config['adms_user_table']` is an allow-listed terminal override, not
 * provider configuration. These tests pin its edit-page hydration, its survival
 * through an unrelated save, its absence when the selector is left blank on
 * create, and its fail-closed behaviour on unreadable ciphertext.
 */
class BiometricDeviceUserTableFormTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        config(['biometrics.adms.pbkdf2_iterations' => 1000]);

        $this->operator = User::factory()->create();
        $this->gym = Gym::factory()->create();
        $this->gym->users()->attach($this->operator->id, ['role' => DefaultRole::GYM_OWNER->value]);

        setPermissionsTeamId($this->gym->id);
        $role = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');
        $this->operator->assignRole($role);

        $permissions = collect([
            'view_any_biometric_device',
            'create_biometric_device',
            'update_biometric_device',
        ])->map(fn (string $name): Permission => Permission::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        $role->permissions()->syncWithoutDetaching($permissions->pluck('id'));

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->operator);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_a_stored_user_table_override_is_hydrated_onto_the_edit_form(): void
    {
        $device = $this->makeDeviceWithOverride('user');

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->assertSuccessful()
            ->assertFormSet([
                'provider_config.adms_user_table' => 'user',
            ]);
    }

    public function test_saving_an_unrelated_field_preserves_the_stored_override(): void
    {
        $device = $this->makeDeviceWithOverride('user');

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm(['device_name' => 'Renamed without touching the user table'])
            ->call('save')
            ->assertHasNoFormErrors();

        $device->refresh();

        $this->assertSame('user', $device->provider_config['adms_user_table'] ?? null);
    }

    public function test_leaving_the_selector_blank_on_create_does_not_write_an_override(): void
    {
        Livewire::test(CreateBiometricDevice::class)
            ->fillForm([
                'device_name' => 'Inheriting Device',
                'device_serial' => 'SN-INHERIT-001',
                'device_type' => BiometricTypeEnum::Fingerprint->value,
                'purpose' => BiometricDevicePurpose::AttendanceOnly->value,
                'adms_enabled' => true,
                'adms_comm_key' => '123456',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $device = BiometricDevice::query()->where('device_serial', 'SN-INHERIT-001')->firstOrFail();
        $config = is_array($device->provider_config) ? $device->provider_config : [];

        $this->assertArrayNotHasKey(
            'adms_user_table',
            $config,
            'A blank selector must inherit the global default instead of persisting an empty override.'
        );
    }

    public function test_unreadable_ciphertext_does_not_throw_or_hydrate_an_override(): void
    {
        $device = $this->makeDeviceWithOverride('user');

        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update(['provider_config' => 'not-a-valid-encrypted-payload']);

        $component = Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->assertSuccessful();

        $this->assertNull($component->get('data.provider_config.adms_user_table'));
    }

    private function makeDeviceWithOverride(string $userTable): BiometricDevice
    {
        return BiometricDevice::factory()->withCommKey('123456')->create([
            'gym_id' => $this->gym->id,
            'device_serial' => fake()->unique()->numerify('SN-USER-TABLE-####'),
            'provider_type' => null,
            'adms_enabled' => true,
            'provider_config' => ['adms_user_table' => $userTable],
        ]);
    }
}
