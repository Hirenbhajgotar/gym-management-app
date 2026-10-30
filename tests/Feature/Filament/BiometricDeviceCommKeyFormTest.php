<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use Spatie\Permission\PermissionRegistrar;
use App\Biometrics\Services\CommKeyReveal;
use App\Biometrics\Services\DeviceTokenReveal;
use App\Biometrics\Services\AdmsDeviceAuthenticator;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricProvidersEnum;
use App\Enums\BiometricTypeEnum;
use App\Enums\DefaultRole;
use App\Exceptions\ProviderProvenanceException;
use App\Exceptions\UnreadableProviderConfigException;
use App\Filament\Admin\Resources\BiometricDeviceResource;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages\CreateBiometricDevice;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages\EditBiometricDevice;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BiometricDeviceCommKeyFormTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected User $gymOwner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['biometrics.adms.pbkdf2_iterations' => 1000]);

        $this->gymOwner = User::factory()->create();
        $this->gym = Gym::factory()->create();
        $this->gym->users()->attach($this->gymOwner->id, ['role' => DefaultRole::GYM_OWNER->value]);

        setPermissionsTeamId($this->gym->id);
        $role = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');
        $this->gymOwner->assignRole($role);

        $createPermission = Permission::query()->firstOrCreate([
            'name' => 'create_biometric_device',
            'guard_name' => 'web',
        ]);
        $updatePermission = Permission::query()->firstOrCreate([
            'name' => 'update_biometric_device',
            'guard_name' => 'web',
        ]);

        // Attach by id: the team-aware registrar only exposes permissions already
        // attached to a role, so givePermissionTo() cannot resolve a new one.
        $role->permissions()->syncWithoutDetaching([
            $createPermission->id,
            $updatePermission->id,
        ]);

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->gymOwner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /**
     * @return array<string, mixed>
     */
    private function createFormData(string $commKey): array
    {
        return [
            'device_name' => 'Form Test Device',
            'device_serial' => 'SN-FORM-TEST-001',
            'device_type' => BiometricTypeEnum::Fingerprint->value,
            'purpose' => BiometricDevicePurpose::AttendanceOnly->value,
            'adms_enabled' => true,
            'adms_comm_key' => $commKey,
        ];
    }

    public function test_formatted_paste_is_sanitized_without_dropping_digits(): void
    {
        Livewire::test(CreateBiometricDevice::class)
            ->fillForm($this->createFormData('1 2 3 4 5 6'))
            ->call('create')
            ->assertHasNoFormErrors();

        $device = BiometricDevice::query()->where('device_serial', 'SN-FORM-TEST-001')->firstOrFail();

        $this->assertNotNull($device->adms_comm_key_salt);
        $this->assertSame(
            BiometricDevice::hashCommKey('123456', (string) $device->adms_comm_key_salt),
            $device->adms_comm_key_hash
        );
        $this->assertTrue($device->verifyCommKey('123456'));
    }

    public function test_untidy_formatted_paste_is_sanitized_before_hashing(): void
    {
        Livewire::test(CreateBiometricDevice::class)
            ->fillForm($this->createFormData(' 123 456 '))
            ->call('create')
            ->assertHasNoFormErrors();

        $device = BiometricDevice::query()->where('device_serial', 'SN-FORM-TEST-001')->firstOrFail();

        $this->assertSame(
            BiometricDevice::hashCommKey('123456', (string) $device->adms_comm_key_salt),
            $device->adms_comm_key_hash
        );
    }

    public function test_a_five_digit_key_is_rejected_under_the_exactly_six_policy(): void
    {
        Livewire::test(CreateBiometricDevice::class)
            ->fillForm($this->createFormData('12345'))
            ->call('create')
            ->assertHasFormErrors(['adms_comm_key']);

        $this->assertDatabaseMissing('biometric_devices', ['device_serial' => 'SN-FORM-TEST-001']);
    }

    public function test_a_one_character_key_is_rejected_under_the_exactly_six_policy(): void
    {
        Livewire::test(CreateBiometricDevice::class)
            ->fillForm($this->createFormData('1'))
            ->call('create')
            ->assertHasFormErrors(['adms_comm_key']);

        $this->assertDatabaseMissing('biometric_devices', ['device_serial' => 'SN-FORM-TEST-001']);
    }

    public function test_create_stores_a_one_time_reveal_without_writing_it_to_the_session(): void
    {
        Livewire::test(CreateBiometricDevice::class)
            ->fillForm($this->createFormData('123456'))
            ->call('create')
            ->assertHasNoFormErrors();

        $device = BiometricDevice::query()->where('device_serial', 'SN-FORM-TEST-001')->firstOrFail();

        $this->assertSame('123456', CommKeyReveal::pull($device->getKey()));
        $this->assertStringNotContainsString('123456', json_encode(session()->all()) ?: '');

        $token = DeviceTokenReveal::pull($device->getKey());
        $this->assertIsString($token);
        $this->assertStringNotContainsString($token, json_encode(session()->all()) ?: '');
    }

    public function test_a_seven_digit_key_is_rejected(): void
    {
        Livewire::test(CreateBiometricDevice::class)
            ->fillForm($this->createFormData('1234567'))
            ->call('create')
            ->assertHasFormErrors(['adms_comm_key']);

        $this->assertDatabaseMissing('biometric_devices', ['device_serial' => 'SN-FORM-TEST-001']);
    }

    public function test_a_numeric_key_with_a_leading_zero_is_rejected(): void
    {
        Livewire::test(CreateBiometricDevice::class)
            ->fillForm($this->createFormData('012345'))
            ->call('create')
            ->assertHasFormErrors(['adms_comm_key']);

        $this->assertDatabaseMissing('biometric_devices', ['device_serial' => 'SN-FORM-TEST-001']);
    }

    public function test_an_alphanumeric_six_character_key_is_accepted(): void
    {
        Livewire::test(CreateBiometricDevice::class)
            ->fillForm($this->createFormData('ABC123'))
            ->call('create')
            ->assertHasNoFormErrors();

        $device = BiometricDevice::query()->where('device_serial', 'SN-FORM-TEST-001')->firstOrFail();
        $this->assertTrue($device->verifyCommKey('ABC123'));
    }

    public function test_editing_with_a_blank_key_keeps_the_existing_hash_and_legacy_plaintext(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();
        $existingHash = BiometricDevice::hashCommKey('123456', $salt);

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-EDIT-TEST-001',
            'provider_type' => null,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_comm_key_hash' => $existingHash,
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['pushcommkey' => '123456', 'other_setting' => 'safe'],
        ]);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm(['adms_comm_key' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $device->refresh();
        $this->assertSame($existingHash, $device->adms_comm_key_hash);
        // A routine edit is not a cleanup event; the backfill owns verified
        // plaintext removal, so a blank save must not destroy recovery material.
        $this->assertSame('123456', $device->provider_config['pushcommkey'] ?? null);
        $this->assertSame('safe', $device->provider_config['other_setting'] ?? null);
    }

    public function test_editing_with_a_new_key_rotates_the_hash_and_cleans_legacy_plaintext(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-EDIT-TEST-002',
            'provider_type' => null,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['pushcommkey' => '123456'],
        ]);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm(['adms_comm_key' => '654 321'])
            ->call('save')
            ->assertHasNoFormErrors();

        $device->refresh();
        $this->assertTrue($device->verifyCommKey('654321'));
        $this->assertFalse($device->verifyCommKey('123456'));
        $this->assertArrayNotHasKey('pushcommkey', $device->provider_config);
        $this->assertSame('654321', CommKeyReveal::pull($device->getKey()));
    }

    public function test_editing_a_cloud_device_preserves_its_provider_comm_key(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-EDIT-CLOUD-001',
            'provider_type' => BiometricProvidersEnum::Custom,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => [
                'comm_key' => 'cloud-provider-key',
                'api_url' => 'https://cloud.example.test',
                'other_setting' => 'safe',
            ],
        ]);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm(['adms_comm_key' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $device->refresh();
        $this->assertSame('cloud-provider-key', $device->provider_config['comm_key'] ?? null);
        $this->assertSame('safe', $device->provider_config['other_setting'] ?? null);
    }

    public function test_rotating_a_native_device_removes_its_legacy_comm_key_alias(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-EDIT-NATIVE-001',
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['comm_key' => '123456', 'other_setting' => 'safe'],
        ]);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm(['adms_comm_key' => '654321'])
            ->call('save')
            ->assertHasNoFormErrors();

        $device->refresh();
        $this->assertArrayNotHasKey('comm_key', $device->provider_config);
        $this->assertTrue($device->verifyCommKey('654321'));
    }

    public function test_key_rotation_clears_an_existing_lockout(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-EDIT-LOCKOUT-001',
            'provider_type' => null,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => [],
        ]);

        /** @var AdmsDeviceAuthenticator $authenticator */
        $authenticator = resolve(AdmsDeviceAuthenticator::class);
        Cache::put(AdmsDeviceAuthenticator::LOCKOUT_PREFIX . $device->id, true, now()->addMinutes(15));

        $this->assertTrue($authenticator->isLockedOut($device));

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm(['adms_comm_key' => '654321'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($authenticator->isLockedOut($device->refresh()));
    }

    public function test_a_twenty_character_key_is_rejected(): void
    {
        Livewire::test(CreateBiometricDevice::class)
            ->fillForm($this->createFormData(str_repeat('1', 20)))
            ->call('create')
            ->assertHasFormErrors(['adms_comm_key']);

        $this->assertDatabaseMissing('biometric_devices', ['device_serial' => 'SN-FORM-TEST-001']);
    }

    public function test_edit_page_survives_a_corrupt_provider_config_and_preserves_its_ciphertext_on_save(): void
    {
        $rawCiphertext = 'not-a-valid-encrypted-payload';

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-EDIT-CORRUPT-001',
            'provider_type' => null,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'provider_config' => ['pushcommkey' => '123456'],
        ]);

        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update(['provider_config' => $rawCiphertext]);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->assertSuccessful()
            ->fillForm([
                'device_name' => 'Renamed Corrupt Device',
                'adms_comm_key' => '123456',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Provider configuration was not changed');

        $this->assertSame(
            $rawCiphertext,
            DB::table('biometric_devices')->where('id', $device->id)->value('provider_config')
        );

        $this->assertSame('Renamed Corrupt Device', $device->refresh()->device_name);
    }

    public function test_a_cloud_device_with_an_unreadable_provider_config_keeps_its_ciphertext_on_rename(): void
    {
        $rawCiphertext = 'not-a-valid-encrypted-payload';
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-EDIT-CORRUPT-CLOUD-001',
            'provider_type' => BiometricProvidersEnum::Custom->value,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['api_url' => 'https://cloud.example.test'],
        ]);

        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update(['provider_config' => $rawCiphertext]);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->assertSuccessful()
            ->fillForm([
                'device_name' => 'Renamed Cloud Corrupt Device',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Provider configuration was not changed');

        $this->assertSame(
            $rawCiphertext,
            DB::table('biometric_devices')->where('id', $device->id)->value('provider_config')
        );

        $device->refresh();

        $this->assertSame('Renamed Cloud Corrupt Device', $device->device_name);
        $this->assertSame(BiometricProvidersEnum::Custom, $device->provider_type);
    }

    public function test_provider_type_change_is_blocked_while_provider_config_is_unreadable(): void
    {
        $rawCiphertext = 'not-a-valid-encrypted-payload';
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-EDIT-CORRUPT-NATIVE-TO-CLOUD',
            'provider_type' => null,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['comm_key' => '123456'],
        ]);

        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update(['provider_config' => $rawCiphertext]);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm([
                'provider_type' => BiometricProvidersEnum::Custom->value,
            ])
            ->call('save')
            ->assertHasFormErrors(['provider_type']);

        $this->assertSame(
            $rawCiphertext,
            DB::table('biometric_devices')->where('id', $device->id)->value('provider_config')
        );

        $this->assertNull($device->refresh()->provider_type);
    }

    public function test_null_to_device_native_does_not_require_the_unreadable_replace_toggle(): void
    {
        $rawCiphertext = 'not-a-valid-encrypted-payload';

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-EDIT-CORRUPT-NULL-TO-NATIVE',
            'provider_type' => null,
            'adms_enabled' => false,
            'status' => BiometricDeviceStatusEnum::Active,
            'provider_config' => [],
        ]);

        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update(['provider_config' => $rawCiphertext]);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm([
                'provider_type' => BiometricProvidersEnum::DeviceNative->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Provider configuration was not changed');

        $this->assertSame(
            $rawCiphertext,
            DB::table('biometric_devices')->where('id', $device->id)->value('provider_config')
        );

        $this->assertSame(BiometricProvidersEnum::DeviceNative, $device->refresh()->provider_type);
    }

    public function test_model_update_cannot_change_provider_type_while_config_is_unreadable(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-MODEL-CORRUPT-PROVIDER-SWITCH',
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'provider_config' => [],
        ]);

        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update(['provider_config' => 'not-a-valid-encrypted-payload']);

        $device->refresh();
        $device->provider_type = BiometricProvidersEnum::Custom;

        $this->expectException(UnreadableProviderConfigException::class);
        $this->expectExceptionMessage('Cannot change provider type while provider_config is unreadable');

        $device->save();
    }

    public function test_cloud_key_provenance_is_server_derived_on_a_model_update(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-MODEL-CLOUD-TO-NATIVE',
            'provider_type' => BiometricProvidersEnum::Custom,
            'provider_config' => [
                'api_url' => 'https://cloud.example.test',
                'comm_key' => 'cloud-provider-key',
            ],
        ]);

        $device->provider_type = BiometricProvidersEnum::DeviceNative;
        $device->save();

        $this->assertSame('cloud', $device->refresh()->provider_config['comm_key_source'] ?? null);
        $this->assertFalse($device->mayDeleteCommKeyAlias());
    }

    public function test_save_quietly_derives_cloud_key_provenance_on_a_cloud_to_native_switch(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-QUIET-CLOUD-TO-NATIVE',
            'provider_type' => BiometricProvidersEnum::Custom,
            'provider_config' => [
                'api_url' => 'https://cloud.example.test',
                'comm_key' => 'cloud-provider-key',
            ],
        ]);

        $device->provider_type = BiometricProvidersEnum::DeviceNative;
        $device->saveQuietly();

        $fresh = $device->refresh();

        $this->assertSame('cloud', $fresh->provider_config['comm_key_source'] ?? null);
        $this->assertFalse($fresh->mayDeleteCommKeyAlias());
    }

    public function test_submitted_provider_config_cannot_forge_cloud_key_provenance(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-EDIT-FORGED-PROVENANCE',
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'provider_config' => ['comm_key' => '123456'],
        ]);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->set('data.provider_config.comm_key_source', 'cloud')
            ->call('save')
            ->assertHasNoFormErrors();

        $device->refresh();

        $this->assertTrue($device->mayDeleteCommKeyAlias());
        $this->assertArrayNotHasKey('comm_key_source', $device->provider_config);
    }

    public function test_a_direct_model_update_cannot_forge_cloud_key_provenance(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-MODEL-FORGED-PROVENANCE',
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'provider_config' => ['comm_key' => '123456'],
        ]);

        $device->update([
            'provider_config' => [
                'comm_key' => '123456',
                'comm_key_source' => 'cloud',
            ],
        ]);

        $device->refresh();

        $this->assertArrayNotHasKey('comm_key_source', $device->provider_config);
        $this->assertTrue($device->mayDeleteCommKeyAlias());
        $this->assertSame(['comm_key' => '123456'], $device->admsPlaintextCommKeyAliases());
    }

    public function test_save_quietly_cannot_forge_cloud_key_provenance(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-MODEL-QUIET-FORGED-PROVENANCE',
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'provider_config' => ['comm_key' => '123456'],
        ]);

        $device->provider_config = [
            'comm_key' => '123456',
            'comm_key_source' => 'cloud',
        ];
        $device->saveQuietly();

        $device->refresh();

        $this->assertArrayNotHasKey('comm_key_source', $device->provider_config);
        $this->assertTrue($device->mayDeleteCommKeyAlias());
    }

    public function test_save_quietly_cannot_forge_provenance_during_a_native_to_cloud_switch(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-MODEL-QUIET-NATIVE-TO-CLOUD',
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'provider_config' => ['comm_key' => '123456'],
        ]);

        $device->provider_type = BiometricProvidersEnum::Custom;
        $device->provider_config = [
            'comm_key' => '123456',
            'comm_key_source' => 'cloud',
        ];
        $device->saveQuietly();

        $device->refresh();

        $this->assertSame(BiometricProvidersEnum::Custom, $device->provider_type);
        $this->assertArrayNotHasKey('comm_key_source', $device->provider_config);
    }

    public function test_a_cloud_to_native_model_update_records_server_derived_provenance(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-MODEL-CLOUD-TO-NATIVE-UPDATE',
            'provider_type' => BiometricProvidersEnum::Custom,
            'provider_config' => [
                'api_url' => 'https://cloud.example.test',
                'comm_key' => 'cloud-provider-key',
            ],
        ]);

        $device->update(['provider_type' => BiometricProvidersEnum::DeviceNative]);

        $device->refresh();

        $this->assertSame('cloud', $device->provider_config['comm_key_source'] ?? null);
        $this->assertSame('cloud-provider-key', $device->provider_config['comm_key'] ?? null);
        $this->assertFalse($device->mayDeleteCommKeyAlias());
    }

    public function test_rewriting_the_config_preserves_an_already_persisted_cloud_marker(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-MODEL-PERSISTED-PROVENANCE',
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'provider_config' => ['comm_key' => 'cloud-provider-key'],
        ]);

        // Simulate a row written by the saving hook during a cloud→native
        // switch. Rewriting the same config (for example the edit page's merge
        // path) must not erase the server-derived provenance.
        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update([
                'provider_config' => Crypt::encryptString(json_encode([
                    'comm_key' => 'cloud-provider-key',
                    'comm_key_source' => 'cloud',
                ], JSON_THROW_ON_ERROR)),
            ]);

        $device->refresh();
        $device->update([
            'device_name' => 'Renamed without touching provenance',
            'provider_config' => $device->provider_config,
        ]);

        $device->refresh();

        $this->assertSame('cloud', $device->provider_config['comm_key_source'] ?? null);
        $this->assertSame('cloud-provider-key', $device->provider_config['comm_key'] ?? null);
        $this->assertFalse($device->mayDeleteCommKeyAlias());
    }

    public function test_rewording_device_native_as_null_preserves_persisted_cloud_provenance(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-MODEL-NATIVE-TO-NULL-PROVENANCE',
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'provider_config' => ['comm_key' => 'cloud-provider-key'],
        ]);

        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update([
                'provider_config' => Crypt::encryptString(json_encode([
                    'comm_key' => 'cloud-provider-key',
                    'comm_key_source' => 'cloud',
                ], JSON_THROW_ON_ERROR)),
            ]);

        $device->refresh();

        // null and DeviceNative are the same policy, so this is not a provider
        // change and must not erase the stored provenance.
        $device->update([
            'provider_type' => null,
            'provider_config' => $device->provider_config,
        ]);

        $device->refresh();

        $this->assertNull($device->provider_type);
        $this->assertSame('cloud', $device->provider_config['comm_key_source'] ?? null);
        $this->assertFalse($device->mayDeleteCommKeyAlias());
    }

    public function test_an_omitted_marker_preserves_a_persisted_cloud_credential(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-MODEL-OMITTED-MARKER',
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'provider_config' => ['comm_key' => 'CLOUD-KEY-123456'],
        ]);

        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update([
                'provider_config' => Crypt::encryptString(json_encode([
                    'comm_key' => 'CLOUD-KEY-123456',
                    'comm_key_source' => 'cloud',
                ], JSON_THROW_ON_ERROR)),
            ]);

        $device->refresh();

        // An unrelated rewrite that re-submits the credential without the
        // server-derived marker must not make the cloud key look like an ADMS
        // alias, because cleanup could then delete it.
        $device->update([
            'device_name' => 'Renamed without the marker',
            'provider_config' => ['comm_key' => 'CLOUD-KEY-123456'],
        ]);

        $device->refresh();

        $this->assertSame('Renamed without the marker', $device->device_name);
        $this->assertSame('CLOUD-KEY-123456', $device->provider_config['comm_key'] ?? null);
        $this->assertSame('cloud', $device->provider_config['comm_key_source'] ?? null);
        $this->assertFalse($device->mayDeleteCommKeyAlias());
    }

    public function test_replacing_a_persisted_cloud_credential_without_provenance_is_refused(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-MODEL-REPLACED-MARKER',
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'provider_config' => ['comm_key' => 'CLOUD-KEY-123456'],
        ]);

        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update([
                'provider_config' => Crypt::encryptString(json_encode([
                    'comm_key' => 'CLOUD-KEY-123456',
                    'comm_key_source' => 'cloud',
                ], JSON_THROW_ON_ERROR)),
            ]);

        $originalCiphertext = DB::table('biometric_devices')
            ->where('id', $device->id)
            ->value('provider_config');

        $device->refresh();

        try {
            $device->update(['provider_config' => ['comm_key' => 'DIFFERENT-KEY']]);
            $this->fail('A cloud provider credential was replaced without explicit provenance.');
        } catch (ProviderProvenanceException $exception) {
            $this->assertStringContainsString('without explicit comm_key_source provenance', $exception->getMessage());
        }

        $this->assertSame(
            $originalCiphertext,
            DB::table('biometric_devices')->where('id', $device->id)->value('provider_config')
        );

        $device->refresh();

        $this->assertSame('CLOUD-KEY-123456', $device->provider_config['comm_key'] ?? null);
        $this->assertSame('cloud', $device->provider_config['comm_key_source'] ?? null);
        $this->assertFalse($device->mayDeleteCommKeyAlias());
    }

    public function test_native_to_cloud_switch_with_the_replace_toggle_rewrites_the_config(): void
    {
        $rawCiphertext = 'not-a-valid-encrypted-payload';
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-EDIT-CORRUPT-REPLACE-CLOUD',
            'provider_type' => null,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => [],
        ]);

        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update(['provider_config' => $rawCiphertext]);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm([
                'replace_unreadable_provider_config' => true,
                'provider_type' => BiometricProvidersEnum::Custom->value,
                'provider_config' => ['api_url' => 'https://cloud.example.test'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $device->refresh();

        $this->assertFalse($device->providerConfigIsUnreadable());
        $this->assertIsArray($device->provider_config);
        $this->assertSame('https://cloud.example.test', $device->provider_config['api_url'] ?? null);
        $this->assertSame('flux_gym_' . $this->gym->id, $device->provider_config['collection_id'] ?? null);
    }

    public function test_a_pushcommkey_only_native_device_can_switch_to_cloud_without_a_new_key(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-EDIT-PUSHKEY-CLOUD',
            'provider_type' => null,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => ['pushcommkey' => '123456'],
        ]);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm([
                'provider_type' => BiometricProvidersEnum::Custom->value,
                'provider_config' => ['api_url' => 'https://cloud.example.test'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(BiometricProvidersEnum::Custom, $device->refresh()->provider_type);
        $this->assertSame('123456', $device->provider_config['pushcommkey'] ?? null);
    }

    public function test_native_to_cloud_and_back_preserves_the_only_plaintext_alias(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-ROUND-TRIP-NATIVE-CLOUD',
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'adms_enabled' => false,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => ['comm_key' => '123456'],
        ]);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm([
                'adms_enabled' => false,
                'provider_type' => BiometricProvidersEnum::Custom->value,
                'provider_config' => ['api_url' => 'https://cloud.example.test'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $device->refresh();

        $this->assertSame(BiometricProvidersEnum::Custom, $device->provider_type);
        // `comm_key` is an ADMS alias for device-native devices only. Moving it
        // to `pushcommkey` keeps it usable for every provider and keeps a
        // return switch from marking it as a retained cloud credential.
        $this->assertSame('123456', $device->provider_config['pushcommkey'] ?? null);
        $this->assertArrayNotHasKey('comm_key', $device->provider_config);
        $this->assertArrayNotHasKey('comm_key_source', $device->provider_config);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm(['provider_type' => BiometricProvidersEnum::DeviceNative->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $device->refresh();

        $this->assertSame(BiometricProvidersEnum::DeviceNative, $device->provider_type);
        $this->assertSame('123456', $device->provider_config['pushcommkey'] ?? null);
        $this->assertArrayNotHasKey('comm_key', $device->provider_config);
        $this->assertArrayNotHasKey('comm_key_source', $device->provider_config);
        $this->assertTrue($device->hasCommKeyConfigured());
        $this->assertTrue($device->verifyCommKey('123456'));
    }

    public function test_model_update_round_trip_preserves_the_only_plaintext_alias(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-ROUND-TRIP-MODEL-UPDATE',
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'adms_enabled' => false,
            'provider_config' => ['comm_key' => '123456'],
        ]);

        $device->update(['provider_type' => BiometricProvidersEnum::Custom]);

        $device->refresh();

        $this->assertSame('123456', $device->provider_config['pushcommkey'] ?? null);
        $this->assertArrayNotHasKey('comm_key', $device->provider_config);
        $this->assertArrayNotHasKey('comm_key_source', $device->provider_config);

        $device->update(['provider_type' => BiometricProvidersEnum::DeviceNative]);

        $device->refresh();

        $this->assertSame('123456', $device->provider_config['pushcommkey'] ?? null);
        $this->assertArrayNotHasKey('comm_key_source', $device->provider_config);
        $this->assertTrue($device->hasCommKeyConfigured());
        $this->assertTrue($device->verifyCommKey('123456'));
    }

    public function test_save_quietly_moves_the_adms_alias_during_a_native_to_cloud_switch(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-QUIET-NATIVE-TO-CLOUD-ALIAS',
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'adms_enabled' => false,
            'provider_config' => ['comm_key' => '123456'],
        ]);

        $device->provider_type = BiometricProvidersEnum::Custom;
        $device->saveQuietly();

        $device->refresh();

        $this->assertSame('123456', $device->provider_config['pushcommkey'] ?? null);
        $this->assertArrayNotHasKey('comm_key', $device->provider_config);
        $this->assertArrayNotHasKey('comm_key_source', $device->provider_config);
    }

    public function test_disabling_adms_during_a_native_to_cloud_switch_does_not_require_a_key(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-EDIT-DISABLE-ADMS-CLOUD',
            'provider_type' => null,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => ['comm_key' => '123456'],
        ]);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm([
                'adms_enabled' => false,
                'provider_type' => BiometricProvidersEnum::Custom->value,
                'provider_config' => ['api_url' => 'https://cloud.example.test'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $device->refresh();

        $this->assertFalse($device->adms_enabled);
        $this->assertSame(BiometricProvidersEnum::Custom, $device->provider_type);
        // The only plaintext key must survive the switch as an alias that every
        // provider still reads, rather than being stranded under `comm_key`.
        $this->assertSame('123456', $device->provider_config['pushcommkey'] ?? null);
        $this->assertArrayNotHasKey('comm_key', $device->provider_config);
        $this->assertArrayNotHasKey('comm_key_source', $device->provider_config);
    }

    public function test_edit_with_the_explicit_replace_flag_resets_a_corrupt_provider_config(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-EDIT-CORRUPT-002',
            'provider_type' => null,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'provider_config' => [],
        ]);

        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update(['provider_config' => 'not-a-valid-encrypted-payload']);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm([
                'replace_unreadable_provider_config' => true,
                'adms_comm_key' => '123456',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $device->refresh();

        $this->assertFalse($device->providerConfigIsUnreadable());
        $this->assertIsArray($device->provider_config);
    }

    public function test_switching_native_to_cloud_with_only_a_plaintext_alias_requires_a_new_key(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-EDIT-NATIVE-TO-CLOUD',
            'provider_type' => null,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => ['comm_key' => '123456'],
        ]);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm([
                'provider_type' => BiometricProvidersEnum::Custom->value,
                'provider_config' => [
                    'api_url' => 'https://cloud.example.test',
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['adms_comm_key']);

        $device->refresh();

        $this->assertNull($device->provider_type);
        $this->assertSame('123456', $device->provider_config['comm_key'] ?? null);
    }

    public function test_switching_native_to_cloud_with_a_new_key_migrates_the_verifier_and_removes_the_alias(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-EDIT-NATIVE-TO-CLOUD-KEY',
            'provider_type' => null,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => ['comm_key' => '123456'],
        ]);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm([
                'provider_type' => BiometricProvidersEnum::Custom->value,
                'adms_comm_key' => '654321',
                'provider_config' => [
                    'api_url' => 'https://cloud.example.test',
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $device->refresh();

        $this->assertSame(BiometricProvidersEnum::Custom, $device->provider_type);
        $this->assertArrayNotHasKey('comm_key', $device->provider_config);
        $this->assertTrue($device->verifyCommKey('654321'));
    }

    public function test_create_writes_the_verifier_in_the_same_insert_as_the_device_row(): void
    {
        $verifierUpdates = [];

        DB::listen(function ($query) use (&$verifierUpdates): void {
            if (
                str_contains($query->sql, 'update `biometric_devices`')
                && str_contains($query->sql, 'adms_comm_key_hash')
            ) {
                $verifierUpdates[] = $query->sql;
            }
        });

        Livewire::test(CreateBiometricDevice::class)
            ->fillForm($this->createFormData('123456'))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertCount(0, $verifierUpdates);

        $device = BiometricDevice::query()->where('device_serial', 'SN-FORM-TEST-001')->firstOrFail();
        $this->assertTrue($device->verifyCommKey('123456'));
    }

    public function test_create_does_not_persist_a_device_when_the_single_save_fails(): void
    {
        $shouldFail = true;

        BiometricDevice::creating(function () use (&$shouldFail): void {
            if ($shouldFail) {
                $shouldFail = false;

                throw new RuntimeException('simulated save failure');
            }
        });

        try {
            Livewire::test(CreateBiometricDevice::class)
                ->fillForm($this->createFormData('123456'))
                ->call('create');
        } catch (RuntimeException) {
            // Expected: the verifier and the device row share one save.
        }

        $this->assertDatabaseMissing('biometric_devices', ['device_serial' => 'SN-FORM-TEST-001']);
    }

    public function test_switching_a_cloud_device_to_native_with_a_new_key_preserves_the_cloud_alias(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-EDIT-CLOUD-TO-NATIVE',
            'provider_type' => BiometricProvidersEnum::Custom,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => [
                'comm_key' => 'cloud-provider-key',
                'api_url' => 'https://cloud.example.test',
            ],
        ]);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm([
                'provider_type' => BiometricProvidersEnum::DeviceNative->value,
                'adms_comm_key' => '654321',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $device->refresh();

        $this->assertSame(BiometricProvidersEnum::DeviceNative, $device->provider_type);
        // A cloud provider's comm_key is a provider credential: switching the
        // ADMS mode does not delete it, and it must not become a native ADMS
        // verifier (or be deleted by a later rotation/backfill).
        $this->assertSame('cloud-provider-key', $device->provider_config['comm_key'] ?? null);
        $this->assertSame('cloud', $device->provider_config['comm_key_source'] ?? null);
        $this->assertFalse($device->mayDeleteCommKeyAlias());
        $this->assertArrayNotHasKey('comm_key', $device->admsPlaintextCommKeyAliases());
        $this->assertTrue($device->verifyCommKey('654321'));
    }

    public function test_edit_page_form_state_and_snapshot_do_not_contain_stored_credentials(): void
    {
        $pushKey = 'PK-9f3a7c';
        $commKey = 'CK-4b2d8e';

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-EDIT-SNAPSHOT-SECRETS',
            'provider_type' => BiometricProvidersEnum::Rekognition,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'provider_config' => [
                'pushcommkey' => $pushKey,
                'comm_key' => $commKey,
                'webhook_secret' => 'super-secret-webhook',
                'api_key' => 'custom-provider-api-key',
                'secret' => 'custom-provider-secret',
                'similarity_threshold' => 91,
            ],
            'token_hash' => 'token-hash-must-not-be-serialized',
        ]);

        $component = Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()]);

        $data = $component->get('data');
        $providerConfig = is_array($data['provider_config'] ?? null) ? $data['provider_config'] : [];

        $this->assertArrayNotHasKey('pushcommkey', $providerConfig);
        $this->assertArrayNotHasKey('comm_key', $providerConfig);
        $this->assertArrayNotHasKey('device_token', $data);
        $this->assertArrayNotHasKey('token_hash', $data);
        $this->assertTrue(($providerConfig['webhook_secret'] ?? null) === null);
        $this->assertTrue(($providerConfig['api_key'] ?? null) === null);
        $this->assertTrue(($providerConfig['secret'] ?? null) === null);
        $this->assertSame(91, $providerConfig['similarity_threshold'] ?? null);

        $html = $component->html();
        $deviceTokenHash = (string) $device->getRawOriginal('device_token');

        foreach ([$pushKey, $commKey, 'super-secret-webhook', 'custom-provider-api-key', 'custom-provider-secret', $deviceTokenHash, 'token-hash-must-not-be-serialized'] as $secret) {
            $this->assertStringNotContainsString($secret, $html, "The edit-page snapshot leaked [{$secret}].");
        }

        $component
            ->fillForm(['device_name' => 'Renamed without rotating credentials'])
            ->call('save')
            ->assertHasNoFormErrors();

        $device->refresh();

        $this->assertSame('super-secret-webhook', $device->provider_config['webhook_secret'] ?? null);
    }

    public function test_provider_config_form_fields_match_the_shared_map_for_every_provider(): void
    {
        foreach (BiometricProvidersEnum::cases() as $provider) {
            $device = BiometricDevice::factory()->create([
                'gym_id' => $this->gym->id,
                'provider_type' => $provider,
                'adms_enabled' => false,
                'provider_config' => [],
            ]);

            $component = Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()]);
            $form = $component->instance()->getForm('form');

            $this->assertNotNull($form);

            $secretPaths = collect(BiometricDeviceResource::providerConfigSecretFields())
                ->map(static fn (string $field): string => 'provider_config.' . $field)
                ->all();

            $actual = collect(array_keys($form->getFlatFields()))
                ->filter(static fn (string $key): bool => str_starts_with($key, 'provider_config.'))
                ->reject(static fn (string $key): bool => in_array($key, $secretPaths, true))
                ->sort()
                ->values()
                ->all();

            $expected = collect(array_merge(
                ['webhook_signature_header'],
                BiometricDeviceResource::providerConfigFormFields($provider)
            ))
                ->map(static fn (string $field): string => 'provider_config.' . $field)
                ->sort()
                ->values()
                ->all();

            $this->assertSame($expected, $actual, "Provider form drift for {$provider->value}.");
        }
    }

    public function test_the_webhook_secret_field_has_no_inline_generate_action(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'adms_enabled' => true,
            'provider_config' => [],
        ]);

        $component = Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()]);
        $form = $component->instance()->getForm('form');

        $this->assertNotNull($form);
        $field = $form->getComponent('data.provider_config.webhook_secret');

        $this->assertInstanceOf(TextInput::class, $field);
        $this->assertSame([], $field->getHintActions());
        $this->assertStringNotContainsString('value is never shown', (string) $field->getHelperText());
    }

    public function test_non_string_livewire_state_returns_a_validation_error_instead_of_a_type_error(): void
    {
        Livewire::test(CreateBiometricDevice::class)
            ->fillForm($this->createFormData('123456'))
            ->set('data.adms_comm_key', ['not', 'a', 'string'])
            ->call('create')
            ->assertHasFormErrors(['adms_comm_key']);

        $this->assertDatabaseMissing('biometric_devices', ['device_serial' => 'SN-FORM-TEST-001']);
    }
}
