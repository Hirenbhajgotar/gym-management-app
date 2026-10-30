<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use Spatie\Permission\PermissionRegistrar;
use App\Biometrics\Services\DeviceTokenReveal;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Cache\CacheManager;
use App\Biometrics\Services\CommKeyReveal;
use App\Biometrics\Services\WebhookSecretReveal;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages\ListBiometricDevices;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BiometricDeviceRevealActionTest extends TestCase
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

        $updatePermission = Permission::query()->firstOrCreate([
            'name' => 'update_biometric_device',
            'guard_name' => 'web',
        ]);
        $role->permissions()->syncWithoutDetaching([$updatePermission->id]);

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->gymOwner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_reveal_renders_the_key_in_the_modal_and_never_writes_it_to_the_session_store(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Reveal Action Device',
            'device_serial' => 'SN-REVEAL-001',
            'device_type' => BiometricTypeEnum::Fingerprint->value,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'adms_enabled' => true,
            'provider_type' => null,
            'provider_config' => [],
        ]);

        CommKeyReveal::put($device->getKey(), '749201');

        config(['session.driver' => 'database']);
        $this->app->forgetInstance('session');
        $this->app->forgetInstance('session.store');

        Livewire::test(ListBiometricDevices::class)
            ->callTableAction('revealCommKey', $device)
            ->assertHasNoTableActionErrors();

        // The one-time slot is consumed but a re-render can still read the
        // encrypted delivery copy, so the key does not blank mid-copy.
        $viewerKey = (string) $this->gymOwner->id;

        $this->assertNull(CommKeyReveal::pull($device->getKey()));
        $this->assertTrue(CommKeyReveal::exists($device->getKey(), $viewerKey));

        $claim = CommKeyReveal::claimForDisplay($device->getKey(), $viewerKey);
        $this->assertSame('749201', $claim['secret']);

        $html = view('filament.biometric.one-time-secret', [
            'secret' => $claim['secret'],
            'description' => 'ADMS communication key for this device:',
        ])->render();

        $this->assertStringContainsString('749201', $html);
        $this->assertStringContainsString('for="one-time-secret-value"', $html);
        $this->assertStringContainsString('id="one-time-secret-value"', $html);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('x-bind:aria-label', $html);
        $this->assertStringContainsString('Copy one-time secret to clipboard', $html);
        $this->assertStringContainsString('Copied to clipboard', $html);
        $this->assertStringContainsString('Select &amp; copy', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'ADMS device comm key revealed',
        ]);

        session()->save();

        $sessionPayload = DB::table('sessions')->pluck('payload')->implode(' ');
        $this->assertStringNotContainsString('749201', $sessionPayload);
        $this->assertStringNotContainsString('749201', json_encode(session()->all()) ?: '');
    }

    public function test_one_time_secret_blade_escapes_quotes_and_script_tags(): void
    {
        $secret = 'a"b</script><script>alert(1)</script>c';

        $html = view('filament.biometric.one-time-secret', [
            'secret' => $secret,
            'description' => 'Secret:',
            'unavailableAction' => 'Rotate',
        ])->render();

        $this->assertStringNotContainsString('</script><script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;/script&gt;', $html);
        $this->assertStringContainsString('Copy one-time secret to clipboard', $html);
    }

    public function test_regenerating_a_token_stores_it_for_one_time_reveal_and_audits_the_change(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Token Regeneration Device',
            'device_serial' => 'SN-REVEAL-TOKEN-001',
            'device_type' => BiometricTypeEnum::Fingerprint->value,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'adms_enabled' => true,
            'provider_type' => null,
            'provider_config' => [],
        ]);

        Livewire::test(ListBiometricDevices::class)
            ->callTableAction('regenerateToken', $device)
            ->assertHasNoTableActionErrors();

        $this->assertTrue(DeviceTokenReveal::exists($device->getKey()));

        $this->assertDatabaseHas('activity_log', [
            'description' => 'ADMS device API token regenerated',
        ]);

        // The one-time token is renderable through the reveal action.
        Livewire::test(ListBiometricDevices::class)
            ->callTableAction('revealToken', $device)
            ->assertHasNoTableActionErrors();

        $claim = DeviceTokenReveal::claimForDisplay($device->getKey(), (string) $this->gymOwner->id);

        $this->assertIsString($claim['secret']);
        $this->assertSame(64, strlen($claim['secret']));

        $html = view('filament.biometric.one-time-secret', [
            'secret' => $claim['secret'],
            'description' => 'API bearer token for this device:',
            'unavailableAction' => 'Regenerate',
        ])->render();

        $this->assertStringContainsString($claim['secret'], $html);
    }

    public function test_generating_a_webhook_secret_stores_it_for_one_time_reveal(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Webhook Reveal Device',
            'device_serial' => 'SN-REVEAL-WEBHOOK-002',
            'device_type' => BiometricTypeEnum::Fingerprint->value,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'adms_enabled' => true,
            'provider_type' => null,
            'provider_config' => [],
        ]);

        Livewire::test(ListBiometricDevices::class)
            ->callTableAction('generateWebhookSecret', $device)
            ->assertHasNoTableActionErrors();

        $viewerKey = (string) $this->gymOwner->id;

        $this->assertTrue(WebhookSecretReveal::exists($device->getKey(), $viewerKey));

        $claim = WebhookSecretReveal::claimForDisplay($device->getKey(), $viewerKey);
        $secret = $claim['secret'];

        $this->assertIsString($secret);
        $this->assertSame(64, strlen($secret));
        $this->assertSame($secret, $device->refresh()->provider_config['webhook_secret'] ?? null);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'ADMS device webhook secret generated',
        ]);

        $html = view('filament.biometric.one-time-secret', [
            'secret' => $secret,
            'description' => 'Webhook HMAC secret for this device:',
        ])->render();

        $this->assertStringContainsString($secret, $html);
    }

    public function test_webhook_reveal_action_audits_once_and_survives_a_second_render(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Webhook Reveal Audit Device',
            'device_serial' => 'SN-REVEAL-WEBHOOK-AUDIT',
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'adms_enabled' => true,
            'provider_type' => null,
            'provider_config' => ['webhook_secret' => 'stored-secret'],
        ]);

        WebhookSecretReveal::put($device->getKey(), 'stored-secret');

        $first = Livewire::test(ListBiometricDevices::class)
            ->mountTableAction('revealWebhookSecret', $device)
            ->assertSuccessful();

        $first->assertSee('stored-secret');

        $this->assertSame(
            1,
            Activity::query()
                ->where('description', 'ADMS device webhook secret revealed')
                ->count()
        );

        Livewire::test(ListBiometricDevices::class)
            ->mountTableAction('revealWebhookSecret', $device)
            ->assertSuccessful()
            ->assertSee('stored-secret');

        $this->assertSame(
            1,
            Activity::query()
                ->where('description', 'ADMS device webhook secret revealed')
                ->count()
        );
    }

    public function test_webhook_generation_preserves_the_old_secret_when_reveal_preflight_fails(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Webhook Cache Failure Device',
            'device_serial' => 'SN-REVEAL-WEBHOOK-CACHE',
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'adms_enabled' => true,
            'provider_type' => null,
            'provider_config' => ['webhook_secret' => 'old-known-secret'],
        ]);

        $originalCache = Cache::getFacadeRoot();
        $failingCache = new class($this->app) extends CacheManager
        {
            public function __call($method, $parameters)
            {
                if ($method === 'put') {
                    return false;
                }

                return parent::__call($method, $parameters);
            }
        };

        Cache::swap($failingCache);

        try {
            Livewire::test(ListBiometricDevices::class)
                ->callTableAction('generateWebhookSecret', $device)
                ->assertHasNoTableActionErrors()
                ->assertNotified('Webhook secret was not rotated');

            $this->assertSame(
                'old-known-secret',
                $device->refresh()->provider_config['webhook_secret'] ?? null
            );
        } finally {
            Cache::swap($originalCache);
        }
    }

    public function test_generating_a_webhook_secret_does_not_destroy_an_unreadable_provider_config(): void
    {
        $rawCiphertext = 'not-a-valid-encrypted-payload';

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Unreadable Webhook Device',
            'device_serial' => 'SN-REVEAL-WEBHOOK-001',
            'device_type' => BiometricTypeEnum::Fingerprint->value,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'adms_enabled' => true,
            'provider_type' => null,
            'provider_config' => [],
        ]);

        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update(['provider_config' => $rawCiphertext]);

        Livewire::test(ListBiometricDevices::class)
            ->callTableAction('generateWebhookSecret', $device)
            ->assertHasNoTableActionErrors()
            ->assertNotified('Provider configuration is unreadable');

        $this->assertSame(
            $rawCiphertext,
            DB::table('biometric_devices')->where('id', $device->id)->value('provider_config')
        );
    }
}
