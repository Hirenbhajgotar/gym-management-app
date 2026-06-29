<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DefaultRole;
use App\Models\Gym;
use App\Models\Permission;
use App\Models\PermissionSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\RolesAndPermissionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolesAndPermissionsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RolesAndPermissionsService $service;

    protected $userMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RolesAndPermissionsService;

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        // Mock the User
        // $this->userMock = Mockery::mock(User::class);
        // $this->app->instance(User::class, $this->userMock);
    }

    // protected function tearDown(): void
    // {
    //     Mockery::close();
    //     parent::tearDown();
    // }

    #[Test]
    public function it_creates_default_tenant_roles_for_a_gym()
    {
        // Create a user and gym
        $user = User::factory()->create();
        $gym = Gym::factory()->create();

        // Create permissions first (required for the syncPermissions call)
        $permissions = [
            'view_any_user',
            'view_user',
            'create_user',
            'update_user',
            'delete_user',
        ];

        foreach ($permissions as $permissionName) {
            Permission::create([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        // Create a permission setting for gym_owner role
        PermissionSetting::query()->create([
            'role' => 'gym_owner',
            'default_permissions' => $permissions,
        ]);

        // Call the service method
        $this->service->createDefaultTenantRolesAndAssignPermissions($user, $gym);

        // Assert that roles were created for the gym
        $tenantRoles = DefaultRole::tenantRoles();
        foreach ($tenantRoles as $role) {
            $this->assertDatabaseHas('roles', [
                'name' => $role->value,
                'guard_name' => 'web',
                'gym_id' => $gym->id,
            ]);
        }

        // Assert that permissions were assigned to the gym_owner role
        $gymOwnerRole = Role::query()->where('name', 'gym_owner')->where('gym_id', $gym->id)->first();
        $this->assertNotNull($gymOwnerRole);
        $this->assertCount(count($permissions), $gymOwnerRole->permissions);
    }

    #[Test]
    public function it_does_not_create_roles_when_user_is_null()
    {
        $gym = Gym::factory()->create();

        // Call the service method with null user
        $this->service->createDefaultTenantRolesAndAssignPermissions(null, $gym);

        // Assert that no roles were created
        $this->assertDatabaseMissing('roles', [
            'gym_id' => $gym->id,
        ]);
    }

    #[Test]
    public function it_does_not_create_roles_when_gym_is_null()
    {
        $user = User::factory()->create();

        // Call the service method with null gym
        $this->service->createDefaultTenantRolesAndAssignPermissions($user);

        // Assert that no roles were created
        $this->assertDatabaseCount('roles', 0);
    }

    #[Test]
    public function it_does_not_create_roles_when_both_user_and_gym_are_null()
    {
        // Call the service method with null user and gym
        $this->service->createDefaultTenantRolesAndAssignPermissions();

        // Assert that no roles were created
        $this->assertDatabaseCount('roles', 0);
    }

    #[Test]
    public function it_assigns_permissions_to_roles_based_on_permission_settings()
    {
        // Create a user and gym
        $user = User::factory()->create();
        $gym = Gym::factory()->create();

        // Create permissions
        $permissions = [
            'view_any_user',
            'view_user',
            'create_user',
        ];

        foreach ($permissions as $permissionName) {
            Permission::create([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        // Create permission settings for all tenant roles
        $tenantRoles = DefaultRole::tenantRoles();
        foreach ($tenantRoles as $role) {
            PermissionSetting::query()->create([
                'role' => $role->value,
                'default_permissions' => $permissions,
            ]);
        }

        // Call the service method
        $this->service->createDefaultTenantRolesAndAssignPermissions($user, $gym);

        // Assert that each role has the correct permissions
        foreach ($tenantRoles as $role) {
            $roleModel = Role::query()->where('name', $role->value)->where('gym_id', $gym->id)->first();
            $this->assertNotNull($roleModel);
            $this->assertCount(count($permissions), $roleModel->permissions);
        }
    }

    #[Test]
    public function it_handles_roles_without_permission_settings()
    {
        // Create a user and gym
        $user = User::factory()->create();
        $gym = Gym::factory()->create();

        // Create permissions
        Permission::create([
            'name' => 'view_any_user',
            'guard_name' => 'web',
        ]);

        // Create a permission setting for only one role
        PermissionSetting::query()->create([
            'role' => 'manager',
            'default_permissions' => ['view_any_user'],
        ]);

        // Call the service method
        $this->service->createDefaultTenantRolesAndAssignPermissions($user, $gym);

        // Assert that all roles were created
        $tenantRoles = DefaultRole::tenantRoles();
        foreach ($tenantRoles as $role) {
            $this->assertDatabaseHas('roles', [
                'name' => $role->value,
                'guard_name' => 'web',
                'gym_id' => $gym->id,
            ]);
        }

        // Assert that only the manager role has permissions
        $managerRole = Role::query()->where('name', 'manager')->where('gym_id', $gym->id)->first();
        $this->assertNotNull($managerRole);
        $this->assertCount(1, $managerRole->permissions);

        // Assert that other roles have no permissions
        $gymOwnerRole = Role::query()->where('name', 'gym_owner')->where('gym_id', $gym->id)->first();
        $this->assertNotNull($gymOwnerRole);
        $this->assertCount(0, $gymOwnerRole->permissions);
    }

    #[Test]
    public function it_handles_empty_permission_settings()
    {
        // Create a user and gym
        $user = User::factory()->create();
        $gym = Gym::factory()->create();

        // Create a permission setting with empty permissions
        PermissionSetting::query()->create([
            'role' => 'gym_owner',
            'default_permissions' => [],
        ]);

        // Call the service method
        $this->service->createDefaultTenantRolesAndAssignPermissions($user, $gym);

        // Assert that roles were created
        $this->assertDatabaseHas('roles', [
            'name' => 'gym_owner',
            'guard_name' => 'web',
            'gym_id' => $gym->id,
        ]);

        // Assert that the role has no permissions
        $gymOwnerRole = Role::query()->where('name', 'gym_owner')->where('gym_id', $gym->id)->first();
        $this->assertNotNull($gymOwnerRole);
        $this->assertCount(0, $gymOwnerRole->permissions);
    }

    #[Test]
    public function it_assigns_role_to_user_when_role_exists()
    {
        // Arrange: Create gym, user, and role
        $gym = Gym::factory()->create();
        $user = User::factory()->create();
        $role = Role::factory()->create([
            'name' => DefaultRole::GYM_OWNER->value,
            'gym_id' => $gym->id,
        ]);

        // Act: Call the method
        $this->service->assignRoleToTenant($gym, $user, DefaultRole::GYM_OWNER);

        // Assert: Check the pivot table directly (most reliable)
        // We cannot use hasRole() here because the global team ID is
        // restored to null after assignRoleToTenant() returns, so
        // Spatie's roles() relationship won't find the role.
        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $role->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);

        // Assert: The team foreign key (gym_id) is set correctly
        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $role->id,
            'model_type' => User::class,
            'model_id' => $user->id,
            'gym_id' => $gym->id,
        ]);
    }

    #[Test]
    public function it_throws_exception_when_role_does_not_exist()
    {
        // Arrange: Create gym and user, no role
        $gym = Gym::factory()->create();
        $user = User::factory()->create();

        $service = new RolesAndPermissionsService;

        // Act & Assert: Expect exception when role is missing
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Role ' . DefaultRole::GYM_OWNER->value . ' does not exist for this tenant.');

        $service->assignRoleToTenant($gym, $user, DefaultRole::GYM_OWNER);
    }
}
