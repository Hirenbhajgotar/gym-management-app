<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DefaultRole;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdmsDeviceCommandPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gymA;

    protected Gym $gymB;

    protected User $ownerA;

    protected User $superAdmin;

    protected BiometricDevice $deviceA;

    protected BiometricDevice $deviceB;

    protected AdmsDeviceCommand $commandA;

    protected AdmsDeviceCommand $commandB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gymA = Gym::factory()->create();
        $this->gymB = Gym::factory()->create();

        $ownerRole = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');
        $superAdminRole = Role::findOrCreate(DefaultRole::SUPER_ADMIN->value, 'web');

        $this->ownerA = User::factory()->create();
        $this->gymA->users()->attach($this->ownerA->id, ['role' => DefaultRole::GYM_OWNER->value]);
        $this->ownerA->assignRole($ownerRole);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole($superAdminRole);

        $this->deviceA = BiometricDevice::factory()->create(['gym_id' => $this->gymA->id]);
        $this->deviceB = BiometricDevice::factory()->create(['gym_id' => $this->gymB->id]);

        $this->commandA = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gymA->id,
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => '100', 'Name' => 'Member A'],
            'status' => 'pending',
        ]);

        $this->commandB = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gymB->id,
            'device_id' => $this->deviceB->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => '200', 'Name' => 'Member B'],
            'status' => 'pending',
        ]);
    }

    public function test_view_any_allowed_for_super_admin_owner_and_manager_but_denied_for_receptionist(): void
    {
        $managerRole = Role::findOrCreate(DefaultRole::MANAGER->value, 'web');
        $receptionistRole = Role::findOrCreate(DefaultRole::RECEPTIONIST->value, 'web');

        $manager = User::factory()->create();
        $this->gymA->users()->attach($manager->id, ['role' => DefaultRole::MANAGER->value]);
        $manager->assignRole($managerRole);

        $receptionist = User::factory()->create();
        $this->gymA->users()->attach($receptionist->id, ['role' => DefaultRole::RECEPTIONIST->value]);
        $receptionist->assignRole($receptionistRole);

        $this->assertTrue($this->superAdmin->can('viewAny', AdmsDeviceCommand::class));
        $this->assertTrue($this->ownerA->can('viewAny', AdmsDeviceCommand::class));
        $this->assertTrue($manager->can('viewAny', AdmsDeviceCommand::class));
        $this->assertFalse($receptionist->can('viewAny', AdmsDeviceCommand::class));
    }

    public function test_gym_owner_can_view_and_resend_own_command_but_denied_other_gym_command(): void
    {
        $this->assertTrue($this->ownerA->can('view', $this->commandA));
        $this->assertTrue($this->ownerA->can('resend', $this->commandA));

        // Tenant isolation: Owner A cannot view or resend Gym B's commands
        $this->assertFalse($this->ownerA->can('view', $this->commandB));
        $this->assertFalse($this->ownerA->can('resend', $this->commandB));
    }

    public function test_super_admin_can_access_commands_across_any_gym(): void
    {
        $this->assertTrue($this->superAdmin->can('view', $this->commandA));
        $this->assertTrue($this->superAdmin->can('view', $this->commandB));
        $this->assertTrue($this->superAdmin->can('resend', $this->commandA));
        $this->assertTrue($this->superAdmin->can('resend', $this->commandB));
        $this->assertTrue($this->superAdmin->can('update', $this->commandA));
        $this->assertTrue($this->superAdmin->can('update', $this->commandB));
    }

    public function test_gym_owner_can_update_own_command_but_denied_other_gym_command(): void
    {
        $this->assertTrue($this->ownerA->can('update', $this->commandA));
        $this->assertFalse($this->ownerA->can('update', $this->commandB));
    }

    public function test_delete_and_delete_any_only_allowed_for_super_admin(): void
    {
        $this->assertFalse($this->ownerA->can('delete', $this->commandA));
        $this->assertFalse($this->ownerA->can('deleteAny', AdmsDeviceCommand::class));

        $this->assertTrue($this->superAdmin->can('delete', $this->commandA));
        $this->assertTrue($this->superAdmin->can('delete', $this->commandB));
        $this->assertTrue($this->superAdmin->can('deleteAny', AdmsDeviceCommand::class));
    }
}
