<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricProvidersEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Pages\BiometricDevice\EnrollMember;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Permission;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnrollMemberTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;

    protected Gym $gymA;

    protected BiometricDevice $deviceA;

    protected GymMember $memberA;

    protected User $userB;

    protected Gym $gymB;

    protected BiometricDevice $deviceB;

    protected GymMember $memberB;

    protected function setUp(): void
    {
        parent::setUp();

        // Gym A setup
        $this->gymA = Gym::factory()->create();
        setPermissionsTeamId($this->gymA->id);

        $role = Role::findOrCreate(DefaultRole::SUPER_ADMIN->value, 'web');
        $permission = Permission::query()->firstOrCreate([
            'name' => 'page_enroll_member',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);

        $this->userA = User::factory()->create();
        $this->gymA->users()->attach($this->userA->id, ['role' => DefaultRole::GYM_OWNER->value]);
        $this->userA->assignRole($role);
        $this->userA->givePermissionTo($permission);

        $this->deviceA = BiometricDevice::factory()->create([
            'gym_id' => $this->gymA->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'device_name' => 'Gym A Entrance <script>alert(1)</script>',
            'device_serial' => 'SN-GYM-A-01',
            'adms_enabled' => true,
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
        ]);

        $this->memberA = GymMember::factory()->create([
            'gym_id' => $this->gymA->id,
            'name' => 'Alice Member A',
        ]);

        // Gym B setup
        $this->userB = User::factory()->create();
        $this->gymB = Gym::factory()
            ->hasAttached($this->userB, ['role' => DefaultRole::GYM_OWNER->value], 'users')
            ->create();

        $this->deviceB = BiometricDevice::factory()->create([
            'gym_id' => $this->gymB->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'device_name' => 'Gym B Entrance',
            'device_serial' => 'SN-GYM-B-01',
            'adms_enabled' => true,
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
        ]);

        $this->memberB = GymMember::factory()->create([
            'gym_id' => $this->gymB->id,
            'name' => 'Bob Member B',
        ]);
    }

    public function test_get_devices_and_members_only_return_tenant_records(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->userA);
        Filament::setTenant($this->gymA);

        $page = new EnrollMember();
        $devices = $page->getDevices();
        $this->assertArrayHasKey($this->deviceA->id, $devices);
        $this->assertArrayNotHasKey($this->deviceB->id, $devices);

        $page->selectedDeviceId = $this->deviceA->id;
        $members = $page->getMembers();
        $this->assertArrayHasKey($this->memberA->id, $members);
        $this->assertArrayNotHasKey($this->memberB->id, $members);
    }

    public function test_cannot_enroll_member_from_another_tenant(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->userA);
        Filament::setTenant($this->gymA);

        $validBase64 = base64_encode('sample-biometric-template-data');

        // Attempting to enroll Gym B's member while in Gym A tenant fails validation
        Livewire::test(EnrollMember::class)
            ->set('selectedDeviceId', $this->deviceA->id)
            ->set('selectedMemberId', $this->memberB->id)
            ->set('biometricType', 'fingerprint')
            ->set('rawBiometricData', $validBase64)
            ->call('enroll')
            ->assertHasErrors(['selectedMemberId']);

        $this->assertDatabaseMissing('biometric_templates', [
            'gym_member_id' => $this->memberB->id,
            'gym_id' => $this->gymA->id,
        ]);
    }

    public function test_cannot_enroll_to_device_from_another_tenant(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->userA);
        Filament::setTenant($this->gymA);

        $validBase64 = base64_encode('sample-biometric-template-data');

        // Attempting to enroll using Gym B's device while in Gym A tenant fails validation
        Livewire::test(EnrollMember::class)
            ->set('selectedDeviceId', $this->deviceB->id)
            ->set('selectedMemberId', $this->memberA->id)
            ->set('biometricType', 'fingerprint')
            ->set('rawBiometricData', $validBase64)
            ->call('enroll')
            ->assertHasErrors(['selectedDeviceId']);
    }

    public function test_successful_enrollment_cancels_pending_and_sent_commands_and_marks_superseded(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->userA);
        Filament::setTenant($this->gymA);

        // Pre-existing pending deletion template
        $oldTemplate = BiometricTemplate::factory()->create([
            'gym_id' => $this->gymA->id,
            'gym_member_id' => $this->memberA->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => 'fingerprint',
            'template_ref' => 'PIN_CUSTOM_123',
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        // Pre-existing commands: one PENDING with custom pin, one SENT with member id pin
        $pendingCmd = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gymA->id,
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => 'PIN_CUSTOM_123'],
            'idempotency_key' => 'user_delete_1',
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        $sentCmd = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gymA->id,
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->memberA->id],
            'idempotency_key' => 'user_delete_2',
            'status' => AdmsCommandStatusEnum::SENT,
        ]);

        $validBase64 = base64_encode('sample-biometric-template-data');

        Livewire::test(EnrollMember::class)
            ->set('selectedDeviceId', $this->deviceA->id)
            ->set('selectedMemberId', $this->memberA->id)
            ->set('biometricType', 'fingerprint')
            ->set('rawBiometricData', $validBase64)
            ->set('sendToDeviceEnabled', false)
            ->call('enroll')
            ->assertHasNoErrors();

        // Old template must be marked Superseded, not Deleted
        $oldTemplate->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Superseded, $oldTemplate->sync_status);

        // Both PENDING and SENT delete commands must be marked CANCELLED with cleared idempotency_key
        $pendingCmd->refresh();
        $sentCmd->refresh();
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $pendingCmd->status);
        $this->assertNull($pendingCmd->idempotency_key);
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $sentCmd->status);
        $this->assertNull($sentCmd->idempotency_key);

        // New template exists and is active
        $this->assertDatabaseHas('biometric_templates', [
            'gym_id' => $this->gymA->id,
            'gym_member_id' => $this->memberA->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'is_active' => true,
        ]);
    }

    public function test_send_to_device_enqueues_user_add_with_deterministic_idempotency_key(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->userA);
        Filament::setTenant($this->gymA);

        $validBase64 = base64_encode('sample-biometric-template-data');

        Livewire::test(EnrollMember::class)
            ->set('selectedDeviceId', $this->deviceA->id)
            ->set('selectedMemberId', $this->memberA->id)
            ->set('biometricType', 'fingerprint')
            ->set('rawBiometricData', $validBase64)
            ->set('sendToDeviceEnabled', true)
            ->call('enroll')
            ->assertHasNoErrors();

        $newTemplate = BiometricTemplate::query()
            ->where('gym_id', $this->gymA->id)
            ->where('gym_member_id', $this->memberA->id)
            ->where('is_active', true)
            ->firstOrFail();

        $this->assertDatabaseHas('adms_device_commands', [
            'gym_id' => $this->gymA->id,
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_add',
            'idempotency_key' => "user_add:{$this->deviceA->id}:{$this->memberA->id}:{$newTemplate->id}",
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);
    }

    public function test_get_members_excludes_members_already_actively_enrolled_on_device(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->userA);
        Filament::setTenant($this->gymA);

        // Active template for memberA on deviceA
        BiometricTemplate::factory()->create([
            'gym_id' => $this->gymA->id,
            'gym_member_id' => $this->memberA->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => 'fingerprint',
            'is_active' => true,
        ]);

        // Unenrolled member
        $memberUnenrolled = GymMember::factory()->create([
            'gym_id' => $this->gymA->id,
            'name' => 'Charlie Unenrolled',
        ]);

        $component = Livewire::test(EnrollMember::class)
            ->set('selectedDeviceId', $this->deviceA->id);

        /** @var EnrollMember $instance */
        $instance = $component->instance();
        $members = $instance->getMembers();

        $this->assertArrayNotHasKey($this->memberA->id, $members);
        $this->assertArrayHasKey($memberUnenrolled->id, $members);
    }
}
