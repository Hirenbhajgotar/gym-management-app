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
use Livewire\Livewire;
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
            'adms_comm_key_salt' => $salt,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('secretKey123', $salt),
            'created_by' => $this->gymOwner->id,
        ]);

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
