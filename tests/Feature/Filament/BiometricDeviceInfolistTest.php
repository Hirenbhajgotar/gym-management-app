<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\BiometricDeviceResource;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages\ListBiometricDevices;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Infolists\Infolist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BiometricDeviceInfolistTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected User $gymOwner;

    protected BiometricDevice $admsDevice;

    protected BiometricDevice $nonAdmsDevice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gymOwner = User::factory()->create();
        $this->gym = Gym::factory()->create();
        $this->gym->users()->attach($this->gymOwner->id, ['role' => DefaultRole::GYM_OWNER->value]);

        setPermissionsTeamId($this->gym->id);
        $role = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');
        $this->gymOwner->assignRole($role);

        $salt = BiometricDevice::generateCommKeySalt();
        $this->admsDevice = BiometricDevice::query()->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Main Turnstile ADMS',
            'device_serial' => 'ZK-TEST-999',
            'device_type' => BiometricTypeEnum::Fingerprint,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'adms_enabled' => true,
            'created_by' => $this->gymOwner->id,
        ]);
        $this->admsDevice->forceFill([
            'adms_comm_key_salt' => $salt,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('secretKey123', $salt),
        ])->saveQuietly();

        $this->nonAdmsDevice = BiometricDevice::query()->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Side Gate Camera',
            'device_serial' => 'CAM-TEST-111',
            'device_type' => BiometricTypeEnum::Face,
            'purpose' => BiometricDevicePurpose::DoorAccess,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'adms_enabled' => false,
            'created_by' => $this->gymOwner->id,
        ]);
    }

    public function test_gym_owner_can_see_biometric_devices_and_actions(): void
    {
        $this->actingAs($this->gymOwner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListBiometricDevices::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->admsDevice, $this->nonAdmsDevice])
            ->assertTableActionExists('view')
            ->assertTableActionExists('admsGuide', record: $this->admsDevice)
            ->assertTableActionDoesNotExist('admsGuide', record: $this->nonAdmsDevice);
    }

    public function test_infolist_schema_contains_adms_configuration_sections(): void
    {
        $infolist = (new Infolist())->record($this->admsDevice);
        $configuredInfolist = BiometricDeviceResource::infolist($infolist);

        $components = $configuredInfolist->getComponents();

        $this->assertNotEmpty($components);

        // Verify sections exist
        $sectionHeadings = collect($components)->map(fn ($section) => $section->getHeading())->all();
        $this->assertContains('Device Overview', $sectionHeadings);
        $this->assertContains('ADMS Protocol & Terminal Configuration', $sectionHeadings);
        $this->assertContains('API & Dual-Channel Webhook', $sectionHeadings);
    }

    public function test_a_valid_comm_key_is_reported_as_configured_even_when_provider_config_is_unreadable(): void
    {
        DB::table('biometric_devices')
            ->where('id', $this->admsDevice->id)
            ->update(['provider_config' => 'not-a-valid-encrypted-payload']);

        $this->actingAs($this->gymOwner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListBiometricDevices::class)
            ->mountTableAction('admsGuide', $this->admsDevice->refresh())
            ->assertSuccessful()
            ->assertSee('Configured')
            ->assertDontSee('Unreadable — re-enter or rotate the key');
    }

    public function test_a_garbage_hash_is_not_reported_as_configured(): void
    {
        $this->admsDevice->forceFill([
            'adms_comm_key_hash' => 'not-a-real-verifier',
            'adms_comm_key_salt' => null,
        ])->saveQuietly();

        $this->actingAs($this->gymOwner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListBiometricDevices::class)
            ->mountTableAction('admsGuide', $this->admsDevice->refresh())
            ->assertSuccessful()
            ->assertSee('Unrecognized verifier')
            ->assertDontSee('Configured');
    }

    public function test_a_versioned_hash_without_a_salt_is_not_reported_as_configured(): void
    {
        $this->admsDevice->forceFill([
            'adms_comm_key_hash' => BiometricDevice::PBKDF2_HASH_PREFIX . ':1000:' . str_repeat('a', 64),
            'adms_comm_key_salt' => null,
        ])->saveQuietly();

        $this->actingAs($this->gymOwner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListBiometricDevices::class)
            ->mountTableAction('admsGuide', $this->admsDevice->refresh())
            ->assertSuccessful()
            ->assertSee('Unrecognized verifier')
            ->assertDontSee('Configured');
    }

    public function test_a_salted_unversioned_hash_is_reported_as_configured(): void
    {
        $salt = 'legacy-salt';

        $this->admsDevice->forceFill([
            'adms_comm_key_hash' => hash_pbkdf2('sha256', 'secretKey123', $salt, BiometricDevice::MIN_PBKDF2_ITERATIONS),
            'adms_comm_key_salt' => $salt,
        ])->saveQuietly();

        $this->assertTrue($this->admsDevice->refresh()->commKeyVerifierIsUnversioned());

        $this->actingAs($this->gymOwner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListBiometricDevices::class)
            ->mountTableAction('admsGuide', $this->admsDevice->refresh())
            ->assertSuccessful()
            ->assertSee('Configured (unversioned)')
            ->assertDontSee('Unrecognized verifier');
    }

    public function test_a_non_string_webhook_secret_is_reported_as_invalid_not_configured(): void
    {
        $config = $this->admsDevice->provider_config;
        $config['webhook_secret'] = ['not-a-string'];
        $this->admsDevice->forceFill(['provider_config' => $config])->saveQuietly();

        $status = BiometricDeviceResource::webhookSecretStatus($this->admsDevice->refresh());

        $this->assertSame('Invalid — regenerate', $status['label']);
        $this->assertSame('danger', $status['color']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function webhookSecretStrengthCases(): array
    {
        return [
            'one character' => ['a', 'Weak — regenerate', 'danger'],
            'whitespace only' => [str_repeat(' ', 32), 'Weak — regenerate', 'danger'],
            '31 characters' => [str_repeat('a', 31), 'Weak — regenerate', 'danger'],
            '32 valid characters' => [str_repeat('a', 32), 'Configured', 'success'],
            '64 hex characters' => [str_repeat('0123456789abcdef', 4), 'Configured', 'success'],
            'legacy short value' => ['legacy-short-secret', 'Weak — regenerate', 'danger'],
        ];
    }

    #[DataProvider('webhookSecretStrengthCases')]
    public function test_webhook_secret_status_marks_weak_values_for_regeneration(
        string $secret,
        string $expectedLabel,
        string $expectedColor
    ): void {
        $config = $this->admsDevice->provider_config;
        $config['webhook_secret'] = $secret;
        $this->admsDevice->forceFill(['provider_config' => $config])->saveQuietly();

        $status = BiometricDeviceResource::webhookSecretStatus($this->admsDevice->refresh());

        $this->assertSame($expectedLabel, $status['label']);
        $this->assertSame($expectedColor, $status['color']);
    }

    public function test_tenant_isolation_prevents_seeing_other_gym_devices(): void
    {
        $otherGym = Gym::factory()->create();
        $otherDevice = BiometricDevice::query()->create([
            'gym_id' => $otherGym->id,
            'device_name' => 'Other Gym Turnstile',
            'device_serial' => 'OTHER-SN-001',
            'device_type' => BiometricTypeEnum::Fingerprint,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'adms_enabled' => true,
            'created_by' => $this->gymOwner->id,
        ]);

        $this->actingAs($this->gymOwner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListBiometricDevices::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->admsDevice])
            ->assertCanNotSeeTableRecords([$otherDevice]);
    }
}
