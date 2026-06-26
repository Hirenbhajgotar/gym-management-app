<?php

namespace Tests\Feature\Filament\StaffPanel;

use Spatie\Permission\Contracts\Permission;
use App\Models\Gym;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class StaffPanelTestCase extends TestCase
{
    protected Gym $testGym;

    protected User $adminUser;

    protected User $managerUser;

    protected User $receptionistUser;

    protected function setUp(): void
    {
        parent::setUp();

        $database = $this->app->make('config')->get('database.connections.mysql.database');

        if ($database === 'gym_app') {
            $this->fail('SAFETY: Tests are targeting the production database "gym_app". Aborting to prevent data loss.');
        }

        $this->testGym = Gym::factory()->create([
            'name' => 'Test Gym',
            'slug' => 'test-gym',
        ]);

        $this->adminUser = User::factory()->create([
            'name' => 'Test Admin',
            'email' => 'admin@test-gym.com',
        ]);

        $this->managerUser = User::factory()->create([
            'name' => 'Test Manager',
            'email' => 'manager@test-gym.com',
        ]);

        $this->receptionistUser = User::factory()->create([
            'name' => 'Test Receptionist',
            'email' => 'receptionist@test-gym.com',
        ]);

        foreach ([
            'gym_owner' => $this->adminUser,
            'manager' => $this->managerUser,
            'receptionist' => $this->receptionistUser,
        ] as $roleName => $user) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
                'gym_id' => $this->testGym->id,
            ]);

            setPermissionsTeamId($this->testGym->id);
            $user->assignRole($role);

            if (! $user->gyms()->where('gym_id', $this->testGym->id)->exists()) {
                $user->gyms()->attach($this->testGym, [
                    'role' => $roleName,
                    'active' => true,
                ]);
                $user->load('gyms');
            }
        }

        setPermissionsTeamId($this->testGym->id);
        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        Filament::setCurrentPanel(Filament::getPanel('staff'));
    }

    protected function grantPermission(User $user, string $name): Permission
    {
        $permission = \App\Models\Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]);

        setPermissionsTeamId($this->testGym->id);

        foreach ($user->roles as $role) {
            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permission;
    }

    protected function actAsAdmin(): self
    {
        Auth::login($this->adminUser);
        setPermissionsTeamId($this->testGym->id);

        return $this;
    }

    protected function actAsManager(): self
    {
        Auth::login($this->managerUser);
        setPermissionsTeamId($this->testGym->id);

        return $this;
    }

    protected function actAsReceptionist(): self
    {
        Auth::login($this->receptionistUser);
        setPermissionsTeamId($this->testGym->id);

        return $this;
    }

    protected function getStaffUrl(string $resource = ''): string
    {
        $base = '/staff/' . $this->testGym->slug;

        return $resource === '' ? $base : $base . '/' . ltrim($resource, '/');
    }

    protected function assertStaffCanAccess(string $resource = ''): void
    {
        $this->get($this->getStaffUrl($resource))
            ->assertSuccessful();
    }

    protected function assertStaffCannotAccess(string $resource = ''): void
    {
        $this->get($this->getStaffUrl($resource))
            ->assertNotFound();
    }
}
