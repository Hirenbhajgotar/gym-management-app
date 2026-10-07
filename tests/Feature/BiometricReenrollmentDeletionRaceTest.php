<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\BiometricDataDeletionService;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Actions\EnrollBiometricAction;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BiometricReenrollmentDeletionRaceTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected GymMember $member;

    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        $this->operator = User::factory()->create();
        $this->gym->users()->attach($this->operator->id, ['role' => DefaultRole::GYM_OWNER->value]);
        $ownerRole = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');
        $this->operator->assignRole($ownerRole);

        $permission = Permission::findOrCreate('update_gym_member', 'web');
        $this->operator->givePermissionTo($permission);

        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'device_type' => 'fingerprint',
        ]);

        $this->member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
        ]);

        BiometricConsent::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
            'revoked_at' => null,
        ]);
    }

    #[Test]
    public function reenrollment_supersedes_pending_deletion_and_reconciler_cancels_stale_hardware_delete(): void
    {
        // 1. Initial template delivered to hardware
        $initialTemplate = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now()->subDay(),
            'hardware_confirmed_at' => now()->subDay(),
        ]);

        // 2. Request single template deletion
        $deletionService = resolve(BiometricDataDeletionService::class);
        $result = $deletionService->deleteSingleTemplate($initialTemplate);

        $this->assertTrue($result);
        $initialTemplate->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $initialTemplate->sync_status);

        // A user_delete command is queued
        $deleteCommand = AdmsDeviceCommand::query()
            ->where('device_id', $this->device->id)
            ->where('command_type', 'user_delete')
            ->first();
        $this->assertNotNull($deleteCommand);
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $deleteCommand->status);

        // 3. Member re-enrolls immediately before deletion command runs
        $this->actingAs($this->operator);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        EnrollBiometricAction::handle($this->member, [
            'device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
        ]);

        // Prior template should now be superseded
        $initialTemplate->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Superseded, $initialTemplate->sync_status);
        $this->assertFalse($initialTemplate->is_active);

        // New template should be created and pending
        $newTemplate = BiometricTemplate::query()
            ->where('gym_member_id', $this->member->id)
            ->where('id', '!=', $initialTemplate->id)
            ->first();
        $this->assertNotNull($newTemplate);
        $this->assertSame(BiometricTemplateSyncStatusEnum::Pending, $newTemplate->sync_status);
        $this->assertFalse($newTemplate->is_active);

        // A new user_add command was created
        $userAddCommand = AdmsDeviceCommand::query()
            ->where('device_id', $this->device->id)
            ->where('command_type', 'user_add')
            ->latest('id')
            ->first();
        $this->assertNotNull($userAddCommand);

        // 4. Run the pending deletion reconciler
        $this->artisan('biometrics:reconcile-pending-deletions')
            ->assertSuccessful();

        // 5. Verify the stale delete command was cancelled and the reconciler did not re-queue any deletion
        $deleteCommand->refresh();
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $deleteCommand->status);
        $this->assertSame(0, AdmsDeviceCommand::query()->where('command_type', 'user_delete')->whereIn('status', [AdmsCommandStatusEnum::PENDING, AdmsCommandStatusEnum::SENT])->count());

        // The new template remains pending and untouched
        $newTemplate->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Pending, $newTemplate->sync_status);
        $this->assertFalse($newTemplate->is_active);
    }
}
