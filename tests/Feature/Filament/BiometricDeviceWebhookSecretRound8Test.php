<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Biometrics\Services\DeviceTokenReveal;
use App\Biometrics\Services\WebhookSecretReveal;
use App\Biometrics\Services\WebhookSecretRotationService;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricProvidersEnum;
use App\Enums\BiometricTypeEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\BiometricDeviceResource;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages\EditBiometricDevice;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages\ListBiometricDevices;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Cache\CacheManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BiometricDeviceWebhookSecretRound8Test extends TestCase
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
            'device_name' => 'Round 8 Webhook Device',
            'device_serial' => 'SN-ROUND8-WEBHOOK',
            'device_type' => BiometricTypeEnum::Fingerprint->value,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'adms_enabled' => true,
            'provider_type' => null,
            'provider_config' => [],
        ], $overrides));
    }

    private function makeSecondGymOwner(): User
    {
        $user = User::factory()->create();
        $this->gym->users()->attach($user->id, ['role' => DefaultRole::GYM_OWNER->value]);

        setPermissionsTeamId($this->gym->id);
        $user->assignRole(Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web'));
        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function failingCacheManager(): CacheManager
    {
        return new class($this->app) extends CacheManager
        {
            public function __call($method, $parameters)
            {
                if ($method === 'put') {
                    return false;
                }

                return parent::__call($method, $parameters);
            }
        };
    }

    public function test_every_secret_field_in_the_shared_map_is_rendered_for_the_providers_that_support_it(): void
    {
        $owners = BiometricDeviceResource::providerConfigSecretFieldOwners();

        $this->assertSame(BiometricDeviceResource::providerConfigSecretFields(), array_keys($owners));
        $this->assertSame(['webhook_signature_header'], BiometricDeviceResource::providerConfigCommonFields());

        foreach (BiometricProvidersEnum::cases() as $provider) {
            $device = $this->makeDevice([
                'device_serial' => 'SN-DRIFT-' . strtoupper($provider->value),
                'provider_type' => $provider,
                'provider_config' => [],
            ]);

            $form = Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
                ->instance()
                ->getForm('form');

            $this->assertNotNull($form);

            $rendered = array_keys($form->getFlatFields());

            foreach (BiometricDeviceResource::providerConfigCommonFields() as $field) {
                $this->assertContains(
                    'provider_config.' . $field,
                    $rendered,
                    "Common field [{$field}] is missing from the {$provider->value} form."
                );
            }

            foreach ($owners as $field => $owner) {
                $this->assertNotSame('', trim($owner['reason']), "Secret field [{$field}] needs a documented reason.");

                $path = 'provider_config.' . $field;
                $supportsField = in_array($provider, $owner['providers'], true);

                if ($supportsField) {
                    $this->assertContains(
                        $path,
                        $rendered,
                        "Secret field [{$field}] is in the shared map and supported by {$provider->value}, but the form does not render it."
                    );

                    continue;
                }

                $this->assertNotContains(
                    $path,
                    $rendered,
                    "Secret field [{$field}] is not supported by {$provider->value} but the form renders it."
                );
            }
        }
    }

    public function test_manual_webhook_secret_accepts_a_strong_value_and_rejects_weak_values(): void
    {
        $this->assertNull(BiometricDeviceResource::manualWebhookSecretValidationError(null));
        $this->assertNull(BiometricDeviceResource::manualWebhookSecretValidationError(''));
        $this->assertNull(BiometricDeviceResource::manualWebhookSecretValidationError(str_repeat('a', 32)));
        $this->assertNull(BiometricDeviceResource::manualWebhookSecretValidationError('Ab1._~+/-=' . str_repeat('x', 30)));

        $this->assertNotNull(BiometricDeviceResource::manualWebhookSecretValidationError(str_repeat('a', 31)));
        $this->assertNotNull(BiometricDeviceResource::manualWebhookSecretValidationError(str_repeat('a', 31) . ' '));
        $this->assertNotNull(BiometricDeviceResource::manualWebhookSecretValidationError(str_repeat('a', 31) . "\t"));
        $this->assertNotNull(BiometricDeviceResource::manualWebhookSecretValidationError(str_repeat('a', 32) . '$'));
        $this->assertNotNull(BiometricDeviceResource::manualWebhookSecretValidationError(['not-a-string']));

        $device = $this->makeDevice(['adms_enabled' => false]);

        foreach ([
            'too-short',
            str_repeat('a', 31),
            str_repeat('a', 32) . ' ',
            str_repeat('a', 32) . '$',
            str_repeat('a', 32) . "\n" . 'b',
        ] as $weakSecret) {
            Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
                ->fillForm(['provider_config' => ['webhook_secret' => $weakSecret]])
                ->call('save')
                ->assertHasFormErrors(['provider_config.webhook_secret']);
        }

        $strongSecret = 'Ab1._~+/-=' . str_repeat('x', 30);

        Livewire::test(EditBiometricDevice::class, ['record' => $device->getRouteKey()])
            ->fillForm(['provider_config' => ['webhook_secret' => $strongSecret]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($strongSecret, $device->refresh()->provider_config['webhook_secret'] ?? null);
    }

    public function test_the_rotator_refuses_an_unreadable_provider_config_even_before_staging(): void
    {
        $device = $this->makeDevice();
        $rawCiphertext = 'not-a-valid-encrypted-payload';

        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update(['provider_config' => $rawCiphertext]);

        $result = resolve(WebhookSecretRotationService::class)->rotateWebhookSecret($device->refresh());

        $this->assertFalse($result['rotated']);
        $this->assertFalse(WebhookSecretReveal::exists($device->getKey()));
        $this->assertFalse(WebhookSecretReveal::hasPending($device->getKey()));
        $this->assertSame(
            $rawCiphertext,
            DB::table('biometric_devices')->where('id', $device->id)->value('provider_config')
        );
    }

    public function test_webhook_generation_fails_closed_while_another_rotation_holds_the_device_lock(): void
    {
        $device = $this->makeDevice([
            'provider_config' => ['webhook_secret' => 'old-known-secret'],
        ]);

        $lock = Cache::lock(WebhookSecretRotationService::webhookSecretLockKey($device->getKey()), 10);
        $this->assertTrue($lock->get());

        try {
            Livewire::test(ListBiometricDevices::class)
                ->callTableAction('generateWebhookSecret', $device)
                ->assertHasNoTableActionErrors()
                ->assertNotified('Webhook secret was not rotated');

            $this->assertSame('old-known-secret', $device->refresh()->provider_config['webhook_secret'] ?? null);
            $this->assertFalse(WebhookSecretReveal::hasPending($device->getKey()));
        } finally {
            $lock->release();
        }

        Livewire::test(ListBiometricDevices::class)
            ->callTableAction('generateWebhookSecret', $device)
            ->assertHasNoTableActionErrors()
            ->assertNotified('Webhook secret generated');

        $device->refresh();
        $claim = WebhookSecretReveal::claimForDisplay($device->getKey(), (string) $this->gymOwner->id);

        $this->assertTrue($claim['claimed']);
        $this->assertSame($device->provider_config['webhook_secret'] ?? null, $claim['secret']);
        $this->assertFalse(WebhookSecretReveal::hasPending($device->getKey()));
    }

    public function test_webhook_generation_preserves_the_old_secret_and_reveal_when_the_database_update_fails(): void
    {
        $device = $this->makeDevice([
            'provider_config' => ['webhook_secret' => 'old-known-secret'],
        ]);

        WebhookSecretReveal::put($device->getKey(), 'old-reveal-secret');

        $event = 'eloquent.updating: ' . BiometricDevice::class;
        Event::listen($event, static function (): void {
            throw new RuntimeException('database unavailable');
        });

        try {
            Livewire::test(ListBiometricDevices::class)
                ->callTableAction('generateWebhookSecret', $device)
                ->assertHasNoTableActionErrors()
                ->assertNotified('Webhook secret was not rotated');
        } finally {
            Event::forget($event);
        }

        $this->assertSame('old-known-secret', $device->refresh()->provider_config['webhook_secret'] ?? null);
        $this->assertFalse(WebhookSecretReveal::hasPending($device->getKey()));
        $this->assertSame('old-reveal-secret', WebhookSecretReveal::pull($device->getKey()));
    }

    public function test_webhook_generation_leaves_the_database_secret_unchanged_when_the_stage_write_fails(): void
    {
        $device = $this->makeDevice([
            'provider_config' => ['webhook_secret' => 'old-known-secret'],
        ]);

        $originalCache = Cache::getFacadeRoot();
        Cache::swap($this->failingCacheManager());

        try {
            Livewire::test(ListBiometricDevices::class)
                ->callTableAction('generateWebhookSecret', $device)
                ->assertHasNoTableActionErrors()
                ->assertNotified('Webhook secret was not rotated');

            $this->assertSame('old-known-secret', $device->refresh()->provider_config['webhook_secret'] ?? null);
            $this->assertFalse(WebhookSecretReveal::hasPending($device->getKey()));
        } finally {
            Cache::swap($originalCache);
        }
    }

    public function test_token_regeneration_does_not_commit_when_the_stage_write_fails(): void
    {
        $oldHash = hash('sha256', 'old-token');

        $device = $this->makeDevice(['token_hash' => $oldHash]);

        $originalCache = Cache::getFacadeRoot();
        Cache::swap($this->failingCacheManager());

        try {
            Livewire::test(ListBiometricDevices::class)
                ->callTableAction('regenerateToken', $device)
                ->assertHasNoTableActionErrors()
                ->assertNotified('Token was not regenerated');

            $this->assertSame($oldHash, $device->refresh()->token_hash);
            $this->assertFalse(DeviceTokenReveal::hasPending($device->getKey()));
        } finally {
            Cache::swap($originalCache);
        }
    }

    public function test_token_regeneration_preserves_the_old_token_when_the_database_update_fails(): void
    {
        $oldHash = hash('sha256', 'old-token');
        $device = $this->makeDevice(['token_hash' => $oldHash]);

        DeviceTokenReveal::put($device->getKey(), 'old-token');

        $event = 'eloquent.updating: ' . BiometricDevice::class;
        Event::listen($event, static function (): void {
            throw new RuntimeException('database unavailable');
        });

        try {
            Livewire::test(ListBiometricDevices::class)
                ->callTableAction('regenerateToken', $device)
                ->assertHasNoTableActionErrors()
                ->assertNotified('Token was not regenerated');
        } finally {
            Event::forget($event);
        }

        $this->assertSame($oldHash, $device->refresh()->token_hash);
        $this->assertFalse(DeviceTokenReveal::hasPending($device->getKey()));
        $this->assertSame('old-token', DeviceTokenReveal::pull($device->getKey()));
    }

    public function test_a_second_operator_cannot_read_or_silently_audit_another_viewers_delivery_copy(): void
    {
        $device = $this->makeDevice([
            'provider_config' => ['webhook_secret' => 'shared-secret'],
        ]);

        WebhookSecretReveal::put($device->getKey(), 'shared-secret');

        Livewire::test(ListBiometricDevices::class)
            ->mountTableAction('revealWebhookSecret', $device)
            ->assertSuccessful()
            ->assertSee('shared-secret');

        $this->assertSame(1, Activity::query()
            ->where('description', 'ADMS device webhook secret revealed')
            ->count());

        $secondOwner = $this->makeSecondGymOwner();
        $secondViewerKey = (string) $secondOwner->id;

        $this->actingAs($secondOwner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertFalse(WebhookSecretReveal::exists($device->getKey(), $secondViewerKey));

        $claim = WebhookSecretReveal::claimForDisplay($device->getKey(), $secondViewerKey);

        $this->assertNull($claim['secret']);
        $this->assertFalse($claim['claimed']);
        $this->assertFalse($claim['first_delivery_read']);

        // The action is hidden for a viewer who has no delivery copy, so the
        // second user cannot even mount the modal, and no secret is rendered.
        Livewire::test(ListBiometricDevices::class)
            ->assertTableActionHidden('revealWebhookSecret', $device)
            ->assertDontSee('shared-secret');

        $this->assertSame(1, Activity::query()
            ->where('description', 'ADMS device webhook secret revealed')
            ->count());

        $this->actingAs($this->gymOwner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListBiometricDevices::class)
            ->mountTableAction('revealWebhookSecret', $device)
            ->assertSuccessful()
            ->assertSee('shared-secret');

        $this->assertSame(1, Activity::query()
            ->where('description', 'ADMS device webhook secret revealed')
            ->count());
    }

    public function test_recording_a_manual_webhook_secret_write_audits_and_forgets_the_old_reveal(): void
    {
        $device = $this->makeDevice([
            'provider_config' => ['webhook_secret' => 'revealed-secret'],
        ]);

        WebhookSecretReveal::put($device->getKey(), 'revealed-secret');
        WebhookSecretReveal::claimForDisplay($device->getKey(), (string) $this->gymOwner->id);

        resolve(WebhookSecretRotationService::class)->recordManualWebhookSecretWrite($device);

        $this->assertFalse(WebhookSecretReveal::exists($device->getKey(), (string) $this->gymOwner->id));
        $this->assertDatabaseHas('activity_log', [
            'description' => 'ADMS device webhook secret set manually',
        ]);
    }

    public function test_one_time_secret_failure_accessible_name_matches_the_visible_label(): void
    {
        $html = view('filament.biometric.one-time-secret', [
            'secret' => '123456',
            'description' => 'Test one-time secret:',
        ])->render();

        $this->assertStringContainsString("failed ? 'Select &amp; copy'", $html);
        $this->assertStringNotContainsString('Copy failed — select the value manually', $html);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
    }
}
