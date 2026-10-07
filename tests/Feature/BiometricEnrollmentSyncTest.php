<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\BiometricDataDeletionService;
use App\Biometrics\Services\BiometricTemplatePropagator;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricProvidersEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Actions\EnrollBiometricAction;
use App\Filament\Admin\Resources\GymMemberResource\Pages\EditGymMember;
use App\Filament\Admin\Resources\GymMemberResource\RelationManagers\BiometricTemplatesRelationManager;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Permission;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BiometricEnrollmentSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected User $owner;

    protected BiometricDevice $deviceA;

    protected BiometricDevice $deviceB;

    protected GymMember $member;

    protected string $plainTokenA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        $superAdminRole = Role::findOrCreate(DefaultRole::SUPER_ADMIN->value, 'web');
        $gymOwnerRole = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');

        foreach ([
            'view_any_gym_member',
            'view_gym_member',
            'create_gym_member',
            'update_gym_member',
            'delete_gym_member',
        ] as $permName) {
            $permission = Permission::query()->firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
            $superAdminRole->givePermissionTo($permission);
            $gymOwnerRole->givePermissionTo($permission);
        }

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        // Isolate owner to GYM_OWNER role only (avoid conflating with SUPER_ADMIN)
        $this->owner = User::factory()->create();
        $this->gym->users()->attach($this->owner->id, ['role' => DefaultRole::GYM_OWNER->value]);
        $this->owner->assignRole($gymOwnerRole);
        $this->actingAs($this->owner);

        $this->plainTokenA = 'token-device-a-12345678';
        $this->deviceA = BiometricDevice::factory()
            ->withToken($this->plainTokenA)
            ->create([
                'gym_id' => $this->gym->id,
                'device_name' => 'Front Door Device',
                'device_serial' => 'SN-FRONT-01',
                'device_type' => 'fingerprint',
                'status' => BiometricDeviceStatusEnum::Active,
                'is_enabled' => true,
                'adms_enabled' => true,
                'provider_type' => BiometricProvidersEnum::DeviceNative,
                'purpose' => BiometricDevicePurpose::AttendanceOnly,
            ]);

        $this->deviceB = BiometricDevice::factory()
            ->withToken('token-device-b-12345678')
            ->create([
                'gym_id' => $this->gym->id,
                'device_name' => 'Back Door Device',
                'device_serial' => 'SN-BACK-02',
                'device_type' => 'fingerprint',
                'status' => BiometricDeviceStatusEnum::Active,
                'is_enabled' => true,
                'adms_enabled' => true,
                'provider_type' => BiometricProvidersEnum::DeviceNative,
                'purpose' => BiometricDevicePurpose::AttendanceOnly,
            ]);

        $this->member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'John Doe',
        ]);
    }

    protected function grantConsent(GymMember $member): BiometricConsent
    {
        return BiometricConsent::query()->create([
            'gym_id' => $member->gym_id,
            'gym_member_id' => $member->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
        ]);
    }

    public function test_enrollment_creates_placeholder_template_and_adms_command_when_consent_exists(): void
    {
        // 1. Give member active consent
        BiometricConsent::query()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
        ]);

        $this->assertTrue($this->member->hasActiveBiometricConsent());

        // 2. Trigger enrollment action
        EnrollBiometricAction::handle($this->member, [
            'device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
        ]);

        // 3. Assert placeholder template exists with sync_status=Pending, is_active=false, and enrolled_at=null
        $this->assertDatabaseHas('biometric_templates', [
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending->value,
            'enrolled_at' => null,
        ]);

        $template = BiometricTemplate::query()
            ->where('gym_member_id', $this->member->id)
            ->where('enrolled_by_device_id', $this->deviceA->id)
            ->firstOrFail();

        $this->assertNull($template->template_ref);
        $this->assertNull($template->enrolled_at);

        // 4. Assert user_add command queued for Device A with attempt-discriminating key
        $expectedKey = "user_add:{$this->gym->id}:{$this->deviceA->id}:{$this->member->id}:{$template->id}";
        $this->assertDatabaseHas('adms_device_commands', [
            'gym_id' => $this->gym->id,
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_add',
            'status' => AdmsCommandStatusEnum::PENDING->value,
            'idempotency_key' => $expectedKey,
        ]);

        // Device B should NOT have any command yet (event-driven sync happens only after confirmation)
        $this->assertDatabaseMissing('adms_device_commands', [
            'device_id' => $this->deviceB->id,
        ]);
    }

    public function test_consent_gate_blocks_enrollment_when_no_consent(): void
    {
        $this->assertFalse($this->member->hasActiveBiometricConsent());

        EnrollBiometricAction::handle($this->member, [
            'device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
        ]);

        $this->assertDatabaseMissing('biometric_templates', [
            'gym_member_id' => $this->member->id,
        ]);

        $this->assertDatabaseMissing('adms_device_commands', [
            'gym_id' => $this->gym->id,
            'device_id' => $this->deviceA->id,
        ]);
    }

    public function test_enroll_sync_updates_placeholder_and_auto_syncs_to_sibling_devices(): void
    {
        BiometricConsent::query()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
        ]);

        // 1. Initiate enrollment (creates placeholder)
        EnrollBiometricAction::handle($this->member, [
            'device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
        ]);

        $placeholder = BiometricTemplate::query()
            ->where('gym_member_id', $this->member->id)
            ->where('enrolled_by_device_id', $this->deviceA->id)
            ->firstOrFail();

        $this->assertSame(BiometricTemplateSyncStatusEnum::Pending, $placeholder->sync_status);
        $this->assertFalse($placeholder->is_active);
        $this->assertNull($placeholder->enrolled_at);

        // 2. Device A confirms capture via /enroll-sync
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->plainTokenA,
        ])->postJson('/api/v1/biometric/enroll-sync', [
            'gym_member_id' => $this->member->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
            'template_ref' => (string) $this->member->id,
            'quality_score' => 0.95,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        // 3. Placeholder template must now be Synced, active, enrolled_at set, and template_ref encrypted
        $placeholder->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Synced, $placeholder->sync_status);
        $this->assertTrue($placeholder->is_active);
        $this->assertNotNull($placeholder->enrolled_at);
        $this->assertSame((string) $this->member->id, $placeholder->template_ref);

        // Verify encrypted cast: raw column in database is encrypted ciphertext
        $rawRow = DB::table('biometric_templates')->where('id', $placeholder->id)->first();
        $this->assertNotNull($rawRow);
        $this->assertNotSame((string) $this->member->id, $rawRow->template_ref);

        // 4. Sibling Device B must now have a user_add command queued with attempt-specific key!
        $expectedSiblingKey = "user_add:{$this->gym->id}:{$this->deviceB->id}:{$this->member->id}:sync:{$placeholder->id}";
        $this->assertDatabaseHas('adms_device_commands', [
            'gym_id' => $this->gym->id,
            'device_id' => $this->deviceB->id,
            'command_type' => 'user_add',
            'status' => AdmsCommandStatusEnum::PENDING->value,
            'idempotency_key' => $expectedSiblingKey,
        ]);

        $siblingCommand = AdmsDeviceCommand::query()
            ->where('device_id', $this->deviceB->id)
            ->where('command_type', 'user_add')
            ->firstOrFail();

        $this->assertIsArray($siblingCommand->command_payload);
        $this->assertArrayHasKey('Pin', $siblingCommand->command_payload);
        $this->assertArrayHasKey('Name', $siblingCommand->command_payload);
        $this->assertSame((string) $this->member->id, $siblingCommand->command_payload['Pin']);
        $this->assertSame($this->member->name, $siblingCommand->command_payload['Name']);
    }

    public function test_re_enrollment_successfully_propagates_to_sibling_devices(): void
    {
        BiometricConsent::query()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
        ]);

        // First enrollment
        EnrollBiometricAction::handle($this->member, [
            'device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->plainTokenA,
        ])->postJson('/api/v1/biometric/enroll-sync', [
            'gym_member_id' => $this->member->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
            'template_ref' => (string) $this->member->id,
        ])->assertOk();

        $firstCommandCount = AdmsDeviceCommand::query()
            ->where('device_id', $this->deviceB->id)
            ->where('command_type', 'user_add')
            ->count();
        $this->assertSame(1, $firstCommandCount);

        // Re-enrollment: second distinct enrollment initiation and confirmation
        EnrollBiometricAction::handle($this->member, [
            'device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->plainTokenA,
        ])->postJson('/api/v1/biometric/enroll-sync', [
            'gym_member_id' => $this->member->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
            'template_ref' => (string) $this->member->id,
        ])->assertOk();

        // A new command should exist on Device B (cancelling previous pending command)
        $activePendingCommandsOnDeviceB = AdmsDeviceCommand::query()
            ->where('device_id', $this->deviceB->id)
            ->where('command_type', 'user_add')
            ->where('status', AdmsCommandStatusEnum::PENDING)
            ->count();

        $this->assertSame(1, $activePendingCommandsOnDeviceB);

        // Total user_add commands on device B is now 2 (one cancelled with cleared key, one pending with new key)
        $commandsOnDeviceB = AdmsDeviceCommand::query()
            ->where('device_id', $this->deviceB->id)
            ->where('command_type', 'user_add')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $commandsOnDeviceB);
        $command0 = $commandsOnDeviceB->get(0);
        $command1 = $commandsOnDeviceB->get(1);
        $this->assertInstanceOf(AdmsDeviceCommand::class, $command0);
        $this->assertInstanceOf(AdmsDeviceCommand::class, $command1);

        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $command0->status);
        $this->assertNull($command0->idempotency_key);

        $this->assertSame(AdmsCommandStatusEnum::PENDING, $command1->status);
        $idempotencyKey1 = $command1->idempotency_key;
        $this->assertNotNull($idempotencyKey1);
        $this->assertStringContainsString(':sync:', $idempotencyKey1);
    }

    public function test_enroll_sync_is_idempotent_for_repeated_identical_calls(): void
    {
        BiometricConsent::query()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
        ]);

        // First call
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->plainTokenA,
        ])->postJson('/api/v1/biometric/enroll-sync', [
            'gym_member_id' => $this->member->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
            'template_ref' => (string) $this->member->id,
        ])->assertOk();

        $countFirst = AdmsDeviceCommand::query()
            ->where('device_id', $this->deviceB->id)
            ->count();
        $this->assertSame(1, $countFirst);

        // Second duplicate call with exact same parameters
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->plainTokenA,
        ])->postJson('/api/v1/biometric/enroll-sync', [
            'gym_member_id' => $this->member->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
            'template_ref' => (string) $this->member->id,
        ])->assertOk();

        $countSecond = AdmsDeviceCommand::query()
            ->where('device_id', $this->deviceB->id)
            ->count();
        $this->assertSame(1, $countSecond);
    }

    public function test_action_is_visible_only_when_active_adms_devices_exist(): void
    {
        $this->assertTrue(EnrollBiometricAction::isVisible($this->member));

        $this->deviceA->update(['adms_enabled' => false]);
        $this->deviceB->update(['adms_enabled' => false]);

        // Clear device availability cache for test
        Cache::forget("biometric:gym:{$this->gym->id}:has_adms_devices");

        $this->assertFalse(EnrollBiometricAction::isVisible($this->member));
    }

    public function test_authorization_allows_owner_manager_receptionist_and_denies_trainer(): void
    {
        // 1. Gym Owner -> Allowed
        $this->actingAs($this->owner);
        $this->assertTrue(EnrollBiometricAction::canAuthorize($this->member));

        // 2. Manager -> Allowed
        $managerRole = Role::findOrCreate(DefaultRole::MANAGER->value, 'web');
        $manager = User::factory()->create();
        $this->gym->users()->attach($manager->id, ['role' => DefaultRole::MANAGER->value]);
        $manager->assignRole($managerRole);

        $this->actingAs($manager);
        $this->assertTrue(EnrollBiometricAction::canAuthorize($this->member));

        // 3. Receptionist -> Allowed
        $receptionistRole = Role::findOrCreate(DefaultRole::RECEPTIONIST->value, 'web');
        $receptionist = User::factory()->create();
        $this->gym->users()->attach($receptionist->id, ['role' => DefaultRole::RECEPTIONIST->value]);
        $receptionist->assignRole($receptionistRole);

        $this->actingAs($receptionist);
        $this->assertTrue(EnrollBiometricAction::canAuthorize($this->member));

        // 4. Trainer -> Strictly Denied (failing-capable check)
        $trainerRole = Role::findOrCreate(DefaultRole::TRAINER->value, 'web');
        $permission = Permission::query()->firstOrCreate([
            'name' => 'update_gym_member',
            'guard_name' => 'web',
        ]);
        $trainerRole->givePermissionTo($permission);

        $trainer = User::factory()->create();
        $this->gym->users()->attach($trainer->id, ['role' => DefaultRole::TRAINER->value]);
        $trainer->assignRole($trainerRole);

        $this->actingAs($trainer);
        // Positive control: trainer has update_gym_member permission
        $this->assertTrue($trainer->can('update_gym_member'));
        // But enrollBiometric strictly denies trainer
        $this->assertFalse(EnrollBiometricAction::canAuthorize($this->member));
    }

    public function test_edit_gym_member_action_enrolls_via_modal_form(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->owner);
        Filament::setTenant($this->gym);

        // 1. Without consent -> Denied
        Livewire::test(EditGymMember::class, ['record' => $this->member->getRouteKey()])
            ->callAction('enroll_biometric', [
                'device_id' => $this->deviceA->id,
                'biometric_type' => 'fingerprint',
            ]);

        $this->assertDatabaseMissing('biometric_templates', [
            'gym_member_id' => $this->member->id,
        ]);

        // 2. With consent -> Permitted & creates placeholder
        BiometricConsent::query()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
        ]);

        Livewire::test(EditGymMember::class, ['record' => $this->member->getRouteKey()])
            ->callAction('enroll_biometric', [
                'device_id' => $this->deviceA->id,
                'biometric_type' => 'fingerprint',
            ])
            ->assertHasNoActionErrors();

        // Initiating device command
        $this->assertDatabaseHas('adms_device_commands', [
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_add',
        ]);

        // Placeholder template created
        $this->assertDatabaseHas('biometric_templates', [
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending->value,
            'is_active' => false,
        ]);
    }

    public function test_edit_gym_member_page_has_enroll_biometric_action(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->owner);
        Filament::setTenant($this->gym);

        Livewire::test(EditGymMember::class, ['record' => $this->member->getRouteKey()])
            ->assertActionExists('enroll_biometric');
    }

    public function test_relation_manager_has_no_create_or_edit_action(): void
    {
        $relationManager = new BiometricTemplatesRelationManager();
        $relationManager->ownerRecord = $this->member;

        $table = $relationManager->table(
            Table::make($relationManager)
        );

        $this->assertEmpty($table->getHeaderActions());
        $this->assertNull($table->getAction('edit'));
    }

    public function test_relation_manager_deprovision_action_is_gated_by_authorization(): void
    {
        $template = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'provider_type' => 'device_native',
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'template_ref' => (string) $this->member->id,
        ]);

        // Trainer is denied
        $trainerRole = Role::findOrCreate(DefaultRole::TRAINER->value, 'web');
        $trainer = User::factory()->create();
        $this->gym->users()->attach($trainer->id, ['role' => DefaultRole::TRAINER->value]);
        $trainer->assignRole($trainerRole);

        $this->actingAs($trainer);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->gym);

        Livewire::test(BiometricTemplatesRelationManager::class, ['ownerRecord' => $this->member, 'pageClass' => EditGymMember::class])
            ->assertTableActionHidden('deprovision', $template);
    }

    public function test_relation_manager_deprovision_action_queues_hardware_deletion(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->owner);
        Filament::setTenant($this->gym);

        $template = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'provider_type' => 'device_native',
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'template_ref' => (string) $this->member->id,
        ]);

        Livewire::test(BiometricTemplatesRelationManager::class, ['ownerRecord' => $this->member, 'pageClass' => EditGymMember::class])
            ->callTableAction('deprovision', $template);

        // The record survives; only the erasure obligation is recorded.
        $template->refresh();
        $this->assertFalse($template->is_active);
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->sync_status);
        $this->assertNull($template->deleted_at);

        // Hardware command user_delete should be queued for deviceA
        $this->assertDatabaseHas('adms_device_commands', [
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_delete',
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);
    }

    public function test_relation_manager_deprovision_action_does_not_queue_hardware_deletion_for_pending_placeholder(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->owner);
        Filament::setTenant($this->gym);

        $pendingTemplate = BiometricTemplate::factory()->pending()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'provider_type' => 'device_native',
        ]);

        $pendingAddCmd = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id, 'Name' => $this->member->name],
            'idempotency_key' => "user_add_test_{$pendingTemplate->id}",
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        Livewire::test(BiometricTemplatesRelationManager::class, ['ownerRecord' => $this->member, 'pageClass' => EditGymMember::class])
            ->callTableAction('deprovision', $pendingTemplate);

        $pendingTemplate->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $pendingTemplate->sync_status);
        $this->assertFalse($pendingTemplate->is_active);

        // Hardware command user_delete must NEVER be queued for a placeholder that never
        // reached the terminal: there is no credential on the device to remove.
        $this->assertDatabaseMissing('adms_device_commands', [
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_delete',
        ]);

        // Prior pending user_add command must be cancelled
        $pendingAddCmd->refresh();
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $pendingAddCmd->status);
    }

    public function test_sibling_fanout_failure_persists_failed_command_and_reports_shortfall(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->owner);
        Filament::setTenant($this->gym);

        $this->grantConsent($this->member);

        $template = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
            'is_active' => false,
        ]);

        // Intercept creation of PENDING user_add command on device B to simulate a transport failure
        AdmsDeviceCommand::creating(function (AdmsDeviceCommand $cmd) {
            throw_if($cmd->device_id === $this->deviceB->id && $cmd->status === AdmsCommandStatusEnum::PENDING, RuntimeException::class, 'Simulated device transport error');
        });

        $result = resolve(BiometricTemplatePropagator::class)
            ->propagateToSiblingDevices($this->deviceA, $this->member, $template);

        $this->assertFalse($result->isFullSuccess());
        $this->assertSame(0, $result->queuedCount);
        $this->assertTrue($result->hasFailures());
        $this->assertContains($this->deviceB->id, $result->failedDeviceIds);
        $this->assertSame(1, $result->totalSiblings);

        // Verify FAILED command was persisted with null idempotency key
        $failedCommand = AdmsDeviceCommand::query()
            ->where('device_id', $this->deviceB->id)
            ->where('command_type', 'user_add')
            ->where('status', AdmsCommandStatusEnum::FAILED)
            ->first();

        $this->assertNotNull($failedCommand);
        $this->assertNull($failedCommand->idempotency_key);

        // command_payload is a durable, non-redacted column and a QueryException raised while
        // binding a user_add payload embeds the member's name, so only the exception class is
        // persisted. Operators get the full detail from the application log instead.
        $failureReason = $failedCommand->command_payload['failure_reason'] ?? null;
        $this->assertSame('unhandled_RuntimeException', $failureReason);
        $this->assertStringNotContainsString('Simulated device transport error', $failureReason);
    }

    public function test_sibling_propagation_creates_sibling_template_and_enables_deprovisioning(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->owner);
        Filament::setTenant($this->gym);

        $this->grantConsent($this->member);

        // Active template on origin device A
        $originTemplate = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
        ]);

        $result = resolve(BiometricTemplatePropagator::class)
            ->propagateToSiblingDevices($this->deviceA, $this->member, $originTemplate);

        $this->assertTrue($result->isFullSuccess());
        $this->assertSame(1, $result->queuedCount);

        // B7: Sibling template must exist in database for device B
        $siblingTemplate = BiometricTemplate::query()
            ->where('gym_id', $this->gym->id)
            ->where('gym_member_id', $this->member->id)
            ->where('enrolled_by_device_id', $this->deviceB->id)
            ->first();

        $this->assertNotNull($siblingTemplate);
        $this->assertSame(BiometricTemplateSyncStatusEnum::Propagated, $siblingTemplate->sync_status);
        $this->assertTrue($siblingTemplate->is_active);

        // GDPR / Consent deletion de-provisions on BOTH origin and sibling devices
        // B1 fix: Propagated status directly triggers hardware de-provisioning without manual promotion!
        $deletionService = resolve(BiometricDataDeletionService::class);
        $deletionService->deleteForGymMember($this->member);

        // Origin device A gets user_delete
        $this->assertDatabaseHas('adms_device_commands', [
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_delete',
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);

        // Sibling device B gets user_delete as well! (B7 fix)
        $this->assertDatabaseHas('adms_device_commands', [
            'device_id' => $this->deviceB->id,
            'command_type' => 'user_delete',
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);
    }

    public function test_resend_propagate_resend_sequence_does_not_duplicate_or_void_pending_commands(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->owner);
        Filament::setTenant($this->gym);

        $this->grantConsent($this->member);

        // 1. Initial FAILED command on device B with null idempotency_key
        $failedCommand = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->deviceB->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id, 'Name' => $this->member->name],
            'status' => AdmsCommandStatusEnum::FAILED,
            'idempotency_key' => null,
        ]);

        // 2. Resend via Filament action: status becomes PENDING, key remains null
        $failedCommand->update([
            'status' => AdmsCommandStatusEnum::PENDING,
            'attempt_count' => 0,
            'idempotency_key' => null,
        ]);

        // 3. New enrollment propagation occurs
        $newTemplate = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
            'is_active' => false,
        ]);

        $propResult = resolve(BiometricTemplatePropagator::class)
            ->propagateToSiblingDevices($this->deviceA, $this->member, $newTemplate);

        $this->assertTrue($propResult->isFullSuccess());

        // Prior resend command is CANCELLED (not voided silently while active)
        $failedCommand->refresh();
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $failedCommand->status);

        // Exactly one PENDING command exists on device B for this member
        $pendingCount = AdmsDeviceCommand::query()
            ->where('device_id', $this->deviceB->id)
            ->where('command_type', 'user_add')
            ->where('status', AdmsCommandStatusEnum::PENDING)
            ->where('command_payload->Pin', (string) $this->member->id)
            ->count();

        $this->assertSame(1, $pendingCount);
    }

    public function test_consent_recheck_blocks_enrollment_even_when_biometric_consents_exists_attribute_is_hydrated(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->owner);
        Filament::setTenant($this->gym);

        // Member initially has active consent
        $consent = BiometricConsent::query()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
        ]);

        // Hydrate member with withExists to simulate GymMemberResource table query
        /** @var GymMember $memberWithHydratedExists */
        $memberWithHydratedExists = GymMember::query()
            ->withExists(['biometricConsents' => function (Builder $q): void {
                $q->whereColumn('biometric_consents.gym_id', 'gym_members.gym_id')
                    ->whereNull('revoked_at');
            }])
            ->findOrFail($this->member->id);

        $this->assertTrue((bool) $memberWithHydratedExists->getAttribute('biometric_consents_exists'));

        // Revoke consent in DB after render / query
        $consent->update([
            'revoked_at' => now(),
        ]);

        $initialTemplateCount = BiometricTemplate::query()->count();
        $initialCommandCount = AdmsDeviceCommand::query()->count();

        // Call handle with stale in-memory member
        EnrollBiometricAction::handle($memberWithHydratedExists, [
            'device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
        ]);

        // Verification: enrollment was blocked
        $this->assertSame($initialTemplateCount, BiometricTemplate::query()->count());
        $this->assertSame($initialCommandCount, AdmsDeviceCommand::query()->count());
        Notification::assertNotified('Biometric Consent Required');
    }

    public function test_propagator_does_not_demote_confirmed_synced_sibling_template(): void
    {
        $this->grantConsent($this->member);

        // Pre-existing Synced template on sibling Device B
        $enrolledAt = now()->subDays(5);
        $siblingTemplate = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceB->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'adms',
            'template_ref' => 'REF_B',
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
            'enrolled_at' => $enrolledAt,
        ]);

        // Template on origin Device A
        $originTemplate = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        /** @var BiometricTemplatePropagator $propagator */
        $propagator = resolve(BiometricTemplatePropagator::class);
        $propagator->propagateToSiblingDevices($this->deviceA, $this->member, $originTemplate);

        $siblingTemplate->refresh();
        // SF-2: Synced status and enrolled_at must be preserved, never demoted to Propagated
        $this->assertSame(BiometricTemplateSyncStatusEnum::Synced, $siblingTemplate->sync_status);
        $this->assertNotNull($siblingTemplate->enrolled_at);
        $this->assertSame($enrolledAt->toDateTimeString(), $siblingTemplate->enrolled_at->toDateTimeString());
    }

    public function test_origin_device_never_receives_sibling_sync_command(): void
    {
        $this->grantConsent($this->member);

        $originTemplate = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        /** @var BiometricTemplatePropagator $propagator */
        $propagator = resolve(BiometricTemplatePropagator::class);
        $propagator->propagateToSiblingDevices($this->deviceA, $this->member, $originTemplate);

        // SF-14: Origin device must NOT receive any sync commands
        $originSyncCommands = AdmsDeviceCommand::query()
            ->where('device_id', $this->deviceA->id)
            ->where('idempotency_key', 'like', '%:sync%')
            ->count();

        $this->assertSame(0, $originSyncCommands);

        // Sibling device B received exactly one sync command
        $siblingSyncCommands = AdmsDeviceCommand::query()
            ->where('device_id', $this->deviceB->id)
            ->where('idempotency_key', 'like', '%:sync%')
            ->count();

        $this->assertSame(1, $siblingSyncCommands);
    }

    /**
     * The sibling fan-out key embeds the *origin* template id, which belongs to a different
     * device's row. This fixture mirrors exactly what `BiometricTemplatePropagator` produces so
     * the acknowledgement path is genuinely exercised.
     */
    public function test_sibling_device_ack_stamps_the_sibling_propagated_template(): void
    {
        $this->grantConsent($this->member);

        $originTemplate = BiometricTemplate::factory()->synced()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
        ]);

        $siblingTemplate = BiometricTemplate::factory()->propagated()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceB->id,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
            'enrolled_at' => null,
            'hardware_confirmed_at' => null,
        ]);

        $this->assertNotSame($originTemplate->id, $siblingTemplate->id);

        resolve(BiometricTemplatePropagator::class)
            ->propagateToSiblingDevices($this->deviceA, $this->member, $originTemplate);

        $siblingCommand = AdmsDeviceCommand::query()
            ->where('device_id', $this->deviceB->id)
            ->where('command_type', 'user_add')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            "user_add:{$this->gym->id}:{$this->deviceB->id}:{$this->member->id}:sync:{$originTemplate->id}",
            $siblingCommand->idempotency_key,
            'The sibling key must carry the origin template id, which is the case that used to break the reconcile.'
        );

        $siblingCommand->update(['status' => AdmsCommandStatusEnum::SENT, 'attempt_count' => 1]);
        $siblingCommand->markAcknowledged('0');

        $siblingTemplate->refresh();
        $this->assertNotNull($siblingTemplate->hardware_confirmed_at);
        $this->assertSame(BiometricTemplateSyncStatusEnum::Propagated, $siblingTemplate->sync_status);
        $this->assertSame('Propagated (Confirmed)', $siblingTemplate->getSyncStatusBadgeLabel());

        // The origin row belongs to another device and must not be touched by a sibling ACK.
        $originTemplate->refresh();
        $this->assertTrue($originTemplate->wasDeliveredToHardware());
    }

    #[Test]
    public function a_user_add_ack_whose_payload_pin_belongs_to_another_member_does_not_stamp_the_template(): void
    {
        $this->grantConsent($this->member);

        $otherMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Someone Else',
        ]);

        $pending = BiometricTemplate::factory()->pending()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $otherMember->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'provider_type' => 'adms',
        ]);

        // The key names this template, but the payload carries a different member's PIN.
        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id, 'Name' => $this->member->name],
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 1,
            'idempotency_key' => "user_add:{$this->gym->id}:{$this->deviceA->id}:{$otherMember->id}:{$pending->id}",
        ]);

        $command->markAcknowledged('0');

        $pending->refresh();
        $this->assertNull($pending->hardware_confirmed_at);
        $this->assertNull($pending->enrolled_at);
        $this->assertSame(BiometricTemplateSyncStatusEnum::Pending, $pending->sync_status);
    }

    public function test_propagator_cancels_in_flight_deletes_and_does_not_resurrect_pending_deletion(): void
    {
        $this->grantConsent($this->member);

        $originTemplate = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        $siblingOldTemplate = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceB->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        $inFlightDelete = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->deviceB->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'status' => AdmsCommandStatusEnum::SENT,
            'idempotency_key' => AdmsDeviceCommand::deleteIdempotencyKeyFor($this->deviceB->id, (string) $this->member->id),
        ]);

        $result = resolve(BiometricTemplatePropagator::class)
            ->propagateToSiblingDevices($this->deviceA, $this->member, $originTemplate);

        $this->assertTrue($result->isFullSuccess());

        // SF-5: In-flight user_delete is cancelled
        $inFlightDelete->refresh();
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $inFlightDelete->status);

        // SF-5: Older PendingDeletion template is superseded rather than resurrected to Propagated
        $siblingOldTemplate->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Superseded, $siblingOldTemplate->sync_status);
        $this->assertFalse($siblingOldTemplate->is_active);

        // A new Propagated template exists for device B with hardware_confirmed_at null
        $siblingNewTemplate = BiometricTemplate::query()
            ->where('gym_member_id', $this->member->id)
            ->where('enrolled_by_device_id', $this->deviceB->id)
            ->where('sync_status', BiometricTemplateSyncStatusEnum::Propagated)
            ->first();

        $this->assertNotNull($siblingNewTemplate);
        $this->assertTrue($siblingNewTemplate->is_active);
        $this->assertNull($siblingNewTemplate->hardware_confirmed_at);
    }

    public function test_sync_from_device_does_not_overwrite_different_biometric_types(): void
    {
        $this->grantConsent($this->member);

        // 1. Confirm Face enrollment
        $responseFace = $this->withHeaders([
            'Authorization' => "Bearer {$this->plainTokenA}",
        ])->postJson('/api/v1/biometric/enroll-sync', [
            'gym_member_id' => $this->member->id,
            'biometric_type' => 'face',
            'template_ref' => (string) $this->member->id,
            'quality_score' => 0.95,
        ]);

        $responseFace->assertOk();

        // 2. Confirm Fingerprint enrollment on the same device
        $responseFingerprint = $this->withHeaders([
            'Authorization' => "Bearer {$this->plainTokenA}",
        ])->postJson('/api/v1/biometric/enroll-sync', [
            'gym_member_id' => $this->member->id,
            'biometric_type' => 'fingerprint',
            'template_ref' => (string) $this->member->id,
            'quality_score' => 0.90,
        ]);

        $responseFingerprint->assertOk();

        // SF-6: Both modalities remain active and neither was retyped or overwritten
        $faceTemplate = BiometricTemplate::query()
            ->where('gym_member_id', $this->member->id)
            ->where('enrolled_by_device_id', $this->deviceA->id)
            ->where('biometric_type', BiometricTypeEnum::Face)
            ->where('is_active', true)
            ->first();

        $fingerprintTemplate = BiometricTemplate::query()
            ->where('gym_member_id', $this->member->id)
            ->where('enrolled_by_device_id', $this->deviceA->id)
            ->where('biometric_type', BiometricTypeEnum::Fingerprint)
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($faceTemplate);
        $this->assertNotNull($fingerprintTemplate);
        $this->assertNotSame($faceTemplate->id, $fingerprintTemplate->id);
    }

    public function test_reconcile_user_delete_does_not_delete_other_members_template(): void
    {
        $member2 = GymMember::factory()->create(['gym_id' => $this->gym->id]);

        $template1 = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        // Member 2 is enrolled under its own PIN, which the acknowledged delete does not touch.
        $template2 = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member2->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $member2->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'status' => AdmsCommandStatusEnum::SENT,
            'idempotency_key' => AdmsDeviceCommand::deleteIdempotencyKeyFor($this->deviceA->id, (string) $this->member->id),
        ]);

        $command->markAcknowledged('0');

        $template1->refresh();
        $template2->refresh();

        // SF-10: Template 1 was scoped and deleted
        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $template1->sync_status);

        // SF-10: Template 2 for member 2 was not erroneously deleted
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template2->sync_status);
    }

    public function test_user_delete_ack_converges_every_template_sharing_the_pin(): void
    {
        $this->grantConsent($this->member);

        $fingerprint = BiometricTemplate::factory()->synced()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
        ]);

        $face = BiometricTemplate::factory()->synced()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
        ]);

        resolve(BiometricDataDeletionService::class)->deleteForGymMember($this->member);

        $command = AdmsDeviceCommand::query()
            ->where('device_id', $this->deviceA->id)
            ->where('command_type', 'user_delete')
            ->firstOrFail();

        $command->update(['status' => AdmsCommandStatusEnum::SENT]);
        $command->markAcknowledged('0');

        // DATA DELETE user Pin=N is user-granular, so both modalities converge together.
        $fingerprint->refresh();
        $face->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $fingerprint->sync_status);
        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $face->sync_status);
    }

    public function test_propagator_refuses_to_fan_out_when_consent_is_revoked(): void
    {
        $originTemplate = BiometricTemplate::factory()->synced()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
        ]);

        // No consent was ever granted.
        $result = resolve(BiometricTemplatePropagator::class)
            ->propagateToSiblingDevices($this->deviceA, $this->member, $originTemplate);

        $this->assertSame(0, $result->queuedCount);
        $this->assertSame([$this->deviceB->id], $result->failedDeviceIds);
        $this->assertTrue($result->totalFanoutFailed());
        $this->assertDatabaseMissing('adms_device_commands', [
            'device_id' => $this->deviceB->id,
            'command_type' => 'user_add',
        ]);
    }

    public function test_propagator_records_an_erasure_override_as_an_auditable_event(): void
    {
        $this->grantConsent($this->member);

        $originTemplate = BiometricTemplate::factory()->synced()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
        ]);

        $pendingDeletion = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceB->id,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        resolve(BiometricTemplatePropagator::class)
            ->propagateToSiblingDevices($this->deviceA, $this->member, $originTemplate);

        $pendingDeletion->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Superseded, $pendingDeletion->sync_status);
        $this->assertSame(
            BiometricTemplate::SUPERSEDED_BY_ERASURE_OVERRIDE,
            $pendingDeletion->superseded_reason
        );
    }

    public function test_re_enrolling_one_modality_does_not_supersede_the_other_modalities_erasure(): void
    {
        $this->grantConsent($this->member);

        EnrollBiometricAction::handle($this->member, [
            'device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Face->value,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->plainTokenA,
        ])->postJson('/api/v1/biometric/enroll-sync', [
            'gym_member_id' => $this->member->id,
            'biometric_type' => BiometricTypeEnum::Face->value,
            'template_ref' => (string) $this->member->id,
        ])->assertOk();

        // The member's fingerprint enrolment is awaiting erasure.
        $fingerprintErasure = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        // Re-enrolling the face modality must not cancel the fingerprint erasure: the PIN is
        // shared across modalities, so a whole-member supersede would silently drop the
        // member's Art. 17 request.
        EnrollBiometricAction::handle($this->member, [
            'device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Face->value,
        ]);

        $fingerprintErasure->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $fingerprintErasure->sync_status);
    }
}
