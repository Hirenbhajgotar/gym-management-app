<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Enums\DefaultRole;
use App\Models\Gym;
use App\Models\Permission;
use App\Models\User;
use App\Services\RolesAndPermissionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

abstract class PermissionsTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $gymOwner;

    protected Gym $gym;

    protected int $gymId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->gymId = $this->gym->id;

        Role::firstOrCreate(
            ['name' => 'gym_owner', 'guard_name' => 'web', 'gym_id' => $this->gymId]
        );

        $this->gymOwner = User::factory()->create();

        setPermissionsTeamId($this->gymId);

        $this->gymOwner->assignRole('gym_owner');

        $this->seedPermissions();
    }

    protected function seedPermissions(): void
    {
        $service = app(RolesAndPermissionsService::class);
        $service->discoverFilamentPermissions();
    }

    protected function createRoleWithPermissions(DefaultRole $role, array $permissionNames = []): Role
    {
        $roleModel = Role::firstOrCreate(
            ['name' => $role->value, 'gym_id' => $this->gymId],
            ['guard_name' => 'web']
        );

        if (! empty($permissionNames)) {
            $permissions = Permission::whereIn('name', $permissionNames)->get();
            $roleModel->syncPermissions($permissions);
        }

        return $roleModel;
    }

    protected function getPermissionsForRole(DefaultRole $role): \Illuminate\Support\Collection
    {
        $roleModel = Role::where('name', $role->value)
            ->where('gym_id', $this->gymId)
            ->first();

        return $roleModel ? $roleModel->permissions : collect();
    }

    protected function actingAsGymOwner(): self
    {
        $this->actingAs($this->gymOwner);

        return $this;
    }
}
