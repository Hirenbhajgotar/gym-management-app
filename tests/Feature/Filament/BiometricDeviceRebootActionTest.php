<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Actions\RebootBiometricDeviceAction;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages\ListBiometricDevices;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ReusesMigratedTestDatabase;
use Tests\TestCase;

class BiometricDeviceRebootActionTest extends TestCase
{
    use ReusesMigratedTestDatabase;

    protected Gym $gym;

    protected User $gymOwner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['biometrics.adms.require_comm_key' => false]);

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
        $viewPermission = Permission::query()->firstOrCreate([
            'name' => 'view_any_biometric_device',
            'guard_name' => 'web',
        ]);
        $role->permissions()->syncWithoutDetaching([$updatePermission->id, $viewPermission->id]);

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->gymOwner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_reboot_action_is_visible_for_adms_device_and_hidden_for_non_adms(): void
    {
        $admsDevice = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'ADMS Terminal',
            'device_serial' => 'SN-ADMS-001',
            'device_type' => BiometricTypeEnum::Fingerprint->value,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
        ]);

        $nonAdmsDevice = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Non-ADMS Terminal',
            'device_serial' => 'SN-NON-ADMS-002',
            'device_type' => BiometricTypeEnum::Fingerprint->value,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => false,
        ]);

        Livewire::test(ListBiometricDevices::class)
            ->assertTableActionVisible(RebootBiometricDeviceAction::ACTION_NAME, $admsDevice)
            ->assertTableActionHidden(RebootBiometricDeviceAction::ACTION_NAME, $nonAdmsDevice);
    }

    public function test_reboot_action_queues_command_with_initiator_id_and_logs_audit(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Test Terminal',
            'device_serial' => 'SN-REBOOT-001',
            'device_type' => BiometricTypeEnum::Fingerprint->value,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
        ]);

        Livewire::test(ListBiometricDevices::class)
            ->callTableAction(RebootBiometricDeviceAction::ACTION_NAME, $device)
            ->assertHasNoTableActionErrors();

        $command = AdmsDeviceCommand::query()
            ->where('device_id', $device->id)
            ->where('command_type', 'reboot')
            ->first();

        $this->assertNotNull($command);
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $command->status);
        $this->assertSame($this->gym->id, $command->gym_id);
        $this->assertSame($device->device_serial, $command->device_serial);
        $this->assertSame($this->gymOwner->id, $command->command_payload['initiated_by_user_id'] ?? null);

        $activity = Activity::query()
            ->where('subject_type', BiometricDevice::class)
            ->where('subject_id', $device->id)
            ->where('description', 'ADMS device reboot command queued')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('adms_device_reboot_queued', $activity->properties['action'] ?? null);
        $this->assertSame($this->gymOwner->id, $activity->properties['initiated_by_user_id'] ?? null);
    }

    public function test_reboot_action_prevents_duplicate_inflight_commands(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Test Terminal',
            'device_serial' => 'SN-REBOOT-002',
            'device_type' => BiometricTypeEnum::Fingerprint->value,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
        ]);

        // Pre-create an in-flight command
        AdmsDeviceCommand::query()->create([
            'gym_id' => $device->gym_id,
            'device_id' => $device->id,
            'device_serial' => $device->device_serial,
            'command_type' => 'reboot',
            'command_payload' => ['initiated_by_user_id' => $this->gymOwner->id],
            'status' => AdmsCommandStatusEnum::PENDING,
            'idempotency_key' => 'existing-key',
        ]);

        Livewire::test(ListBiometricDevices::class)
            ->callTableAction(RebootBiometricDeviceAction::ACTION_NAME, $device)
            ->assertHasNoTableActionErrors();

        // Count should still be 1 (no duplicate queued)
        $this->assertSame(1, AdmsDeviceCommand::query()->where('device_id', $device->id)->where('command_type', 'reboot')->count());
    }

    public function test_device_acknowledgement_sends_database_notification_to_initiator(): void
    {
        $device = BiometricDevice::factory()
            ->admsEnabled('SN-REBOOT-003')
            ->create([
                'gym_id' => $this->gym->id,
                'device_name' => 'Front Desk Terminal',
                'device_type' => BiometricTypeEnum::Fingerprint->value,
                'purpose' => BiometricDevicePurpose::AttendanceOnly,
                'status' => BiometricDeviceStatusEnum::Active,
            ]);

        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $device->gym_id,
            'device_id' => $device->id,
            'device_serial' => $device->device_serial,
            'command_type' => 'reboot',
            'command_payload' => ['initiated_by_user_id' => $this->gymOwner->id],
            'status' => AdmsCommandStatusEnum::SENT,
            'idempotency_key' => 'reboot-test-key-3',
            'sent_at' => now(),
            'leased_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'attempt_count' => 1,
        ]);

        $this->assertSame(0, $this->gymOwner->notifications()->count());

        // Device reports execution result via POST /api/iclock/devicecmd?SN=...
        $response = $this->call(
            'POST',
            "/api/iclock/devicecmd?SN={$device->device_serial}",
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            "ID={$command->id}&Return=0"
        );

        $response->assertStatus(200);
        $this->assertSame('OK', $response->getContent());

        $command->refresh();
        $this->assertSame(AdmsCommandStatusEnum::ACKNOWLEDGED, $command->status);
        $this->assertNotNull($command->command_payload['reboot_notified_at'] ?? null);

        // Verify database notification was created for the user
        $this->assertSame(1, $this->gymOwner->notifications()->count());
        $notification = $this->gymOwner->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('Terminal Rebooted Successfully', $notification->data['title'] ?? null);
        $this->assertStringContainsString('Front Desk Terminal', (string) ($notification->data['body'] ?? ''));
    }

    public function test_device_handshake_after_boot_acknowledges_sent_reboot_and_notifies_initiator(): void
    {
        $device = BiometricDevice::factory()
            ->admsEnabled('SN-REBOOT-004')
            ->create([
                'gym_id' => $this->gym->id,
                'device_name' => 'Main Gate Terminal',
                'device_type' => BiometricTypeEnum::Fingerprint->value,
                'purpose' => BiometricDevicePurpose::AttendanceOnly,
                'status' => BiometricDeviceStatusEnum::Active,
            ]);

        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $device->gym_id,
            'device_id' => $device->id,
            'device_serial' => $device->device_serial,
            'command_type' => 'reboot',
            'command_payload' => ['initiated_by_user_id' => $this->gymOwner->id],
            'status' => AdmsCommandStatusEnum::SENT,
            'idempotency_key' => 'reboot-test-key-4',
            'sent_at' => now(),
            'leased_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'attempt_count' => 1,
        ]);

        $this->assertSame(0, $this->gymOwner->notifications()->count());

        // Device boots and sends GET /api/iclock/cdata?SN=... (handshake)
        $response = $this->get("/api/iclock/cdata?SN={$device->device_serial}");
        $response->assertStatus(200);

        $command->refresh();
        $this->assertSame(AdmsCommandStatusEnum::ACKNOWLEDGED, $command->status);
        $this->assertNotNull($command->command_payload['reboot_notified_at'] ?? null);

        $this->assertSame(1, $this->gymOwner->notifications()->count());
        $notification = $this->gymOwner->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('Terminal Rebooted Successfully', $notification->data['title'] ?? null);
        $this->assertStringContainsString('Main Gate Terminal', (string) ($notification->data['body'] ?? ''));
    }

    public function test_reboot_action_denies_user_from_different_gym(): void
    {
        $otherGym = Gym::factory()->create();
        $otherDevice = BiometricDevice::factory()
            ->admsEnabled('SN-OTHER-GYM-001')
            ->create([
                'gym_id' => $otherGym->id,
                'device_name' => 'Other Gym Terminal',
                'status' => BiometricDeviceStatusEnum::Active,
            ]);

        $this->assertFalse(RebootBiometricDeviceAction::canAuthorize($this->gymOwner, $otherDevice));
        $this->assertFalse($this->gymOwner->can('reboot', $otherDevice));
    }
}
