<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Enums\DefaultRole;
use App\Models\Gym;
use App\Models\Permission;
use App\Models\User;
use App\Services\RolesAndPermissionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
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

        Role::query()->firstOrCreate(['name' => 'gym_owner', 'guard_name' => 'web', 'gym_id' => $this->gymId]);

        $this->gymOwner = User::factory()->create();

        setPermissionsTeamId($this->gymId);

        $this->gymOwner->assignRole('gym_owner');

        $this->seedPermissions();
    }

    protected function seedPermissions(): void
    {
        $service = resolve(RolesAndPermissionsService::class);
        $service->discoverFilamentPermissions();
    }

    protected function createRoleWithPermissions(DefaultRole $role, array $permissionNames = []): Role
    {
        $roleModel = Role::query()->firstOrCreate(['name' => $role->value, 'gym_id' => $this->gymId], ['guard_name' => 'web']);

        if (filled($permissionNames)) {
            $permissions = Permission::query()->whereIn('name', $permissionNames)->get();
            $roleModel->syncPermissions($permissions);
        }

        return $roleModel;
    }

    protected function getPermissionsForRole(DefaultRole $role): Collection
    {
        $roleModel = Role::query()->where('name', $role->value)
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
