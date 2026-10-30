<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use Spatie\Permission\PermissionRegistrar;
use App\Biometrics\Services\CommKeyReveal;
use App\Biometrics\Services\DeviceTokenReveal;
use App\Biometrics\Services\WebhookSecretReveal;
use App\Biometrics\Services\WebhookSecretRotationService;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages\EditBiometricDevice;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages\ListBiometricDevices;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Throwable;

/**
 * Two writers of the same device's encrypted provider_config must not lose one
 * writer's credential, and a failed or late reveal operation must not expose a
 * superseded secret.
 */
class BiometricDeviceCredentialConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected User $gymOwner;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->gymOwner = User::factory()->create();
        $this->gym = Gym::factory()->create();
        $this->gym->users()->attach($this->gymOwner->id, ['role' => DefaultRole::GYM_OWNER->value]);

        setPermissionsTeamId($this->gym->id);
        $role = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');
        $this->gymOwner->assignRole($role);

        $permissions = collect(['view_any_biometric_device', 'update_biometric_device'])
            ->map(static fn (string $name): Permission => Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));

        $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->gymOwner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeDevice(array $overrides = []): BiometricDevice
    {
        return BiometricDevice::factory()->create(array_merge([
            'gym_id' => $this->gym->id,
            'device_name' => 'Concurrency Device',
            'device_serial' => 'SN-CONCURRENCY-001',
            'device_type' => BiometricTypeEnum::Fingerprint->value,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'adms_enabled' => true,
            'provider_type' => null,
            'provider_config' => [],
        ], $overrides));
    }

    public function test_a_manual_webhook_secret_save_is_rejected_while_the_provider_config_lock_is_held(): void
    {
        $device = $this->makeDevice([
            'adms_enabled' => false,
            'provider_config' => ['webhook_secret' => 'stored-secret'],
        ]);

        $lock = Cache::lock(
            WebhookSecretRotationService::providerConfigLockKey($device->getKey()),
            300
        );
        $this->assertTrue($lock->get());

        try {
            Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
                ->fillForm(['provider_config' => ['webhook_secret' => str_repeat('a', 32)]])
                ->call('save')
                ->assertHasErrors(['data.provider_config']);

            $this->assertSame('stored-secret', $device->refresh()->provider_config['webhook_secret'] ?? null);
        } finally {
            $lock->release();
        }

        $newSecret = str_repeat('b', 32);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm(['provider_config' => ['webhook_secret' => $newSecret]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($newSecret, $device->refresh()->provider_config['webhook_secret'] ?? null);
    }

    public function test_a_plain_edit_is_rejected_while_the_provider_config_lock_is_held(): void
    {
        $device = $this->makeDevice([
            'adms_enabled' => false,
            'provider_config' => ['webhook_secret' => 'stored-secret'],
        ]);

        $lock = Cache::lock(
            WebhookSecretRotationService::providerConfigLockKey($device->getKey()),
            300
        );
        $this->assertTrue($lock->get());

        try {
            Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
                ->fillForm(['device_name' => 'Renamed while locked'])
                ->call('save')
                ->assertHasErrors(['data.provider_config']);

            $this->assertSame('Concurrency Device', $device->refresh()->device_name);
        } finally {
            $lock->release();
        }

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm(['device_name' => 'Renamed after lock release'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed after lock release', $device->refresh()->device_name);
    }

    /**
     * The manual save runs inside a nested transaction, so its post-commit
     * publish hook is deferred until after the Generate action has promoted a
     * new reveal. A late forget in that hook would delete the newer secret.
     */
    public function test_a_manual_save_does_not_delete_a_later_generated_reveal(): void
    {
        $device = $this->makeDevice([
            'adms_enabled' => false,
            'provider_config' => ['webhook_secret' => 'old-secret'],
        ]);

        $manualSecret = str_repeat('c', 32);

        DB::beginTransaction();

        try {
            Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
                ->fillForm(['provider_config' => ['webhook_secret' => $manualSecret]])
                ->call('save')
                ->assertHasNoFormErrors();

            Livewire::test(ListBiometricDevices::class)
                ->callTableAction('generateWebhookSecret', $device)
                ->assertHasNoTableActionErrors()
                ->assertNotified('Webhook secret generated');
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        // The manual save's audit is deferred with its publish hook, so it
        // cannot have run before the nested transaction commits.
        $this->assertDatabaseMissing('activity_log', [
            'description' => 'ADMS device webhook secret set manually',
        ]);

        // Commit the nested transaction: this runs the manual save's deferred
        // publish hook, which must not delete the Generate reveal.
        DB::commit();

        // Confirm the deferred hook actually ran; otherwise the reveal check
        // below would pass without exercising the late-forget path.
        $this->assertDatabaseHas('activity_log', [
            'description' => 'ADMS device webhook secret set manually',
        ]);

        $device->refresh();
        $viewerKey = (string) $this->gymOwner->id;

        $this->assertNotSame($manualSecret, $device->provider_config['webhook_secret'] ?? null);
        $this->assertTrue(WebhookSecretReveal::exists($device->getKey(), $viewerKey));

        $claim = WebhookSecretReveal::claimForDisplay($device->getKey(), $viewerKey);

        $this->assertTrue($claim['claimed']);
        $this->assertSame($device->provider_config['webhook_secret'] ?? null, $claim['secret']);
    }

    public function test_a_failed_promote_leaves_no_stale_webhook_or_token_reveal(): void
    {
        $device = $this->makeDevice();
        $viewerKey = (string) $this->gymOwner->id;

        WebhookSecretReveal::put($device->getKey(), 'old-webhook-secret');
        DeviceTokenReveal::put($device->getKey(), 'old-token');

        $this->assertTrue(WebhookSecretReveal::claimForDisplay($device->getKey(), $viewerKey)['claimed']);
        $this->assertTrue(DeviceTokenReveal::claimForDisplay($device->getKey(), $viewerKey)['claimed']);

        // Simulate a rotation whose database write committed but whose staged
        // copy disappeared before promote().
        $this->assertTrue(WebhookSecretReveal::stage($device->getKey(), 'new-webhook-secret'));
        WebhookSecretReveal::forgetPending($device->getKey());
        $this->assertTrue(DeviceTokenReveal::stage($device->getKey(), 'new-token'));
        DeviceTokenReveal::forgetPending($device->getKey());

        $this->assertFalse(WebhookSecretReveal::promote($device->getKey()));
        $this->assertFalse(DeviceTokenReveal::promote($device->getKey()));

        $this->assertFalse(WebhookSecretReveal::exists($device->getKey(), $viewerKey));
        $this->assertFalse(DeviceTokenReveal::exists($device->getKey(), $viewerKey));
        $this->assertNull(WebhookSecretReveal::claimForDisplay($device->getKey(), $viewerKey)['secret']);
        $this->assertNull(DeviceTokenReveal::claimForDisplay($device->getKey(), $viewerKey)['secret']);
    }

    public function test_a_comm_key_rotation_is_rejected_while_the_provider_config_lock_is_held(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = $this->makeDevice([
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['pushcommkey' => '123456'],
        ]);

        $originalHash = $device->adms_comm_key_hash;

        $lock = Cache::lock(
            WebhookSecretRotationService::providerConfigLockKey($device->getKey()),
            300
        );
        $this->assertTrue($lock->get());

        try {
            Livewire::test(ListBiometricDevices::class)
                ->callTableAction('rotateCommKey', $device)
                ->assertHasNoTableActionErrors()
                ->assertNotified('Communication key was not rotated');

            $device->refresh();

            $this->assertSame($originalHash, $device->adms_comm_key_hash);
            $this->assertSame('123456', $device->provider_config['pushcommkey'] ?? null);
            $this->assertFalse(CommKeyReveal::exists($device->getKey(), (string) $this->gymOwner->id));
        } finally {
            $lock->release();
        }

        Livewire::test(ListBiometricDevices::class)
            ->callTableAction('rotateCommKey', $device)
            ->assertHasNoTableActionErrors()
            ->assertNotified('Communication key rotated');

        $device->refresh();

        $this->assertNotSame($originalHash, $device->adms_comm_key_hash);
        $this->assertArrayNotHasKey('pushcommkey', $device->provider_config);
        $this->assertTrue(CommKeyReveal::exists($device->getKey(), (string) $this->gymOwner->id));
    }

    public function test_the_provisioning_command_skips_a_device_whose_provider_config_lock_is_held(): void
    {
        $device = $this->makeDevice([
            'provider_config' => ['threshold' => 0.8],
        ]);

        $lock = Cache::lock(
            WebhookSecretRotationService::providerConfigLockKey($device->getKey()),
            300
        );
        $this->assertTrue($lock->get());

        try {
            $this->artisan('biometrics:provision-webhook-secrets')
                ->expectsOutputToContain('another change held their provider configuration lock')
                ->assertFailed();

            $this->assertNull($device->refresh()->provider_config['webhook_secret'] ?? null);
        } finally {
            $lock->release();
        }

        $this->artisan('biometrics:provision-webhook-secrets')
            ->expectsOutputToContain('1 provisioned')
            ->assertSuccessful();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/',
            (string) ($device->refresh()->provider_config['webhook_secret'] ?? '')
        );
    }
}
