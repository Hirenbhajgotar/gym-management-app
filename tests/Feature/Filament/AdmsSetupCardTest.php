<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use Spatie\Permission\PermissionRegistrar;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricProvidersEnum;
use App\Enums\BiometricTypeEnum;
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
use Tests\TestCase;

class AdmsSetupCardTest extends TestCase
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

    public function test_setup_card_copies_values_with_inline_alpine_and_reports_failures(): void
    {
        $html = view('filament.forms.components.adms-setup-card', [
            'commKeyStatus' => 'Configured',
            'appUrl' => "https://example.test/o'reilly",
        ])->render();

        $this->assertStringNotContainsString('window.ffCopyText', $html);
        $this->assertStringNotContainsString('@js(', $html);
        $this->assertStringContainsString('Copy failed — select manually', $html);
        $this->assertStringContainsString('x-data="', $html);
        $this->assertSame(4, substr_count($html, '@click="copy('));

        $this->assertStringContainsString('aria-label="Copy server address"', $html);
        $this->assertStringContainsString('aria-label="Copy server port"', $html);
        $this->assertStringContainsString('aria-label="Copy web path"', $html);
        $this->assertStringContainsString('aria-label="Copy full handshake endpoint"', $html);

        $this->assertSame(4, substr_count($html, 'role="status"'));
        $this->assertSame(4, substr_count($html, 'aria-live="polite"'));

        // The apostrophe in the app URL is escaped in the inline handler
        // instead of terminating the JavaScript string.
        $this->assertStringContainsString('o\u0027reilly', $html);
        $this->assertStringNotContainsString("o'reilly/api/iclock/cdata", $html);
    }

    public function test_adms_setup_modal_renders_working_inline_copy_buttons(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'ADMS Setup Modal Device',
            'device_serial' => 'SN-ADMS-SETUP-MODAL',
            'device_type' => BiometricTypeEnum::Fingerprint->value,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'adms_enabled' => true,
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'provider_config' => [],
        ]);

        Livewire::test(ListBiometricDevices::class)
            ->mountTableAction('admsGuide', $device)
            ->assertSuccessful()
            ->assertSee('ADMS Terminal Connection Parameters')
            ->assertSee('@click="copy(', false)
            ->assertSee('aria-label="Copy server address"', false)
            ->assertSee('aria-label="Copy server port"', false)
            ->assertSee('aria-label="Copy web path"', false)
            ->assertSee('aria-label="Copy full handshake endpoint"', false)
            ->assertSee('role="status"', false)
            ->assertDontSee('window.ffCopyText');
    }
}
