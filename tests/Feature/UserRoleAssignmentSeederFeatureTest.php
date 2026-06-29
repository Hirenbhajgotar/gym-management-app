<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Trainer;
use App\Models\User;
use Database\Seeders\UserRoleAssignmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRoleAssignmentSeederFeatureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_be_called_via_artisan_command()
    {
        // Create a gym first
        Gym::query()->create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        // Mock the interactive inputs by creating a custom seeder that doesn't require interaction
        $seeder = new class() extends UserRoleAssignmentSeeder
        {
            public function run(): void
            {
                // Override to create super admin without interaction
                $this->createSuperAdminUserProgrammatically();
                $this->callDisplayFinalSummary();
            }

            private function createSuperAdminUserProgrammatically(): void
            {
                // Ensure roles exist
                $this->callEnsureRolesExist();

                // Create super admin user
                $superAdmin = User::query()->create([
                    'name' => 'Test Super Admin',
                    'email' => 'admin@test.com',
                    'password' => bcrypt('password123'),
                    'email_verified_at' => now(),
                ]);

                $superAdmin->assignRole('super_admin');
            }

            private function callEnsureRolesExist($gymId = null): void
            {
                $reflection = new ReflectionClass(parent::class);
                $method = $reflection->getMethod('ensureRolesExist');
                $method->setAccessible(true);
                $method->invoke($this, $gymId);
            }

            private function callDisplayFinalSummary(): void
            {
                $reflection = new ReflectionClass(parent::class);
                $method = $reflection->getMethod('displayFinalSummary');
                $method->setAccessible(true);
                $method->invoke($this);
            }
        };

        // Register the custom seeder
        $this->app->bind(function () use ($seeder): UserRoleAssignmentSeeder {
            return $seeder;
        });

        // Run the seeder via Artisan
        $exitCode = Artisan::call('db:seed', [
            '--class' => UserRoleAssignmentSeeder::class,
        ]);

        $this->assertEquals(0, $exitCode);

        // Verify the super admin was created
        $this->assertDatabaseHas('users', [
            'name' => 'Test Super Admin',
            'email' => 'admin@test.com',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'super_admin',
            'guard_name' => 'web',
            'gym_id' => null,
        ]);

        // Verify user has the role
        $user = User::query()->where('email', 'admin@test.com')->first();
        $this->assertTrue($user->hasRole('super_admin'));
    }

    #[Test]
    public function it_creates_all_required_global_roles()
    {
        $seeder = new class() extends UserRoleAssignmentSeeder
        {
            public function run(): void
            {
                $this->callEnsureRolesExist();
            }

            private function callEnsureRolesExist($gymId = null): void
            {
                $reflection = new ReflectionClass(parent::class);
                $method = $reflection->getMethod('ensureRolesExist');
                $method->setAccessible(true);
                $method->invoke($this, $gymId);
            }
        };

        $this->app->bind(function () use ($seeder): UserRoleAssignmentSeeder {
            return $seeder;
        });

        Artisan::call('db:seed', [
            '--class' => UserRoleAssignmentSeeder::class,
        ]);

        // Verify global roles were created
        $this->assertDatabaseHas('roles', [
            'name' => 'super_admin',
            'guard_name' => 'web',
            'gym_id' => null,
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'gym_owner',
            'guard_name' => 'web',
            'gym_id' => null,
        ]);
    }

    #[Test]
    public function it_creates_tenant_roles_for_existing_gyms()
    {
        $gym1 = Gym::query()->create(['name' => 'Gym 1', 'slug' => 'gym-1']);
        $gym2 = Gym::query()->create(['name' => 'Gym 2', 'slug' => 'gym-2']);

        $seeder = new class() extends UserRoleAssignmentSeeder
        {
            public function run(): void
            {
                $this->callEnsureRolesExist(); // This should create tenant roles for all gyms
            }

            private function callEnsureRolesExist($gymId = null): void
            {
                $reflection = new ReflectionClass(parent::class);
                $method = $reflection->getMethod('ensureRolesExist');
                $method->setAccessible(true);
                $method->invoke($this, $gymId);
            }
        };

        $this->app->bind(function () use ($seeder): UserRoleAssignmentSeeder {
            return $seeder;
        });

        Artisan::call('db:seed', [
            '--class' => UserRoleAssignmentSeeder::class,
        ]);

        $tenantRoles = ['manager', 'receptionist', 'trainer', 'member'];

        foreach ([$gym1, $gym2] as $gym) {
            foreach ($tenantRoles as $role) {
                $this->assertDatabaseHas('roles', [
                    'name' => $role,
                    'guard_name' => 'web',
                    'gym_id' => $gym->id,
                ]);
            }
        }
    }

    #[Test]
    public function it_creates_demo_users_programmatically()
    {
        $gym = Gym::query()->create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        $seeder = new class() extends UserRoleAssignmentSeeder
        {
            public function run(): void
            {
                $gyms = $this->callGetAvailableGyms();
                if (filled($gyms)) {
                    $gym = $gyms[0];
                    $this->callEnsureRolesExist($gym['id']);
                    $this->callCreateDemoUsers($gym['id']);
                }
            }

            private function callGetAvailableGyms(): array
            {
                $reflection = new ReflectionClass(parent::class);
                $method = $reflection->getMethod('getAvailableGyms');
                $method->setAccessible(true);

                return $method->invoke($this);
            }

            private function callEnsureRolesExist($gymId = null): void
            {
                $reflection = new ReflectionClass(parent::class);
                $method = $reflection->getMethod('ensureRolesExist');
                $method->setAccessible(true);
                $method->invoke($this, $gymId);
            }

            private function callCreateDemoUsers($gymId): void
            {
                $reflection = new ReflectionClass(parent::class);
                $method = $reflection->getMethod('createDemoUsers');
                $method->setAccessible(true);
                $method->invoke($this, $gymId);
            }
        };

        $this->app->bind(function () use ($seeder): UserRoleAssignmentSeeder {
            return $seeder;
        });

        Artisan::call('db:seed', [
            '--class' => UserRoleAssignmentSeeder::class,
        ]);

        // Verify demo users were created
        $this->assertDatabaseHas('users', [
            'email' => "owner.gym{$gym->id}@gym.com",
        ]);

        $this->assertDatabaseHas('users', [
            'email' => "john.trainer.gym{$gym->id}@gym.com",
        ]);

        $this->assertDatabaseHas('users', [
            'email' => "emma.member.gym{$gym->id}@gmail.com",
        ]);

        // Verify gym_user pivot entries
        $this->assertDatabaseHas('gym_user', [
            'gym_id' => $gym->id,
            'role' => 'gym_owner',
            'active' => true,
        ]);

        // Verify trainer profile was created
        $this->assertDatabaseHas('trainers', [
            'gym_id' => $gym->id,
            'name' => 'John Trainer',
            'email' => "john.trainer.gym{$gym->id}@gym.com",
        ]);

        // Verify member profile was created
        $this->assertDatabaseHas('gym_members', [
            'gym_id' => $gym->id,
            'name' => 'Emma Member',
            'email' => "emma.member.gym{$gym->id}@gmail.com",
        ]);
    }

    #[Test]
    public function it_handles_multiple_gyms_correctly()
    {
        $gym1 = Gym::query()->create(['name' => 'Gym 1', 'slug' => 'gym-1']);
        $gym2 = Gym::query()->create(['name' => 'Gym 2', 'slug' => 'gym-2']);

        $seeder = new class() extends UserRoleAssignmentSeeder
        {
            public function run(): void
            {
                $this->callEnsureRolesExist(); // Create roles for all gyms

                $gyms = $this->callGetAvailableGyms();
                foreach ($gyms as $gym) {
                    $this->callCreateDemoUsers($gym['id']);
                }
            }

            private function callGetAvailableGyms(): array
            {
                $reflection = new ReflectionClass(parent::class);
                $method = $reflection->getMethod('getAvailableGyms');
                $method->setAccessible(true);

                return $method->invoke($this);
            }

            private function callEnsureRolesExist($gymId = null): void
            {
                $reflection = new ReflectionClass(parent::class);
                $method = $reflection->getMethod('ensureRolesExist');
                $method->setAccessible(true);
                $method->invoke($this, $gymId);
            }

            private function callCreateDemoUsers($gymId): void
            {
                $reflection = new ReflectionClass(parent::class);
                $method = $reflection->getMethod('createDemoUsers');
                $method->setAccessible(true);
                $method->invoke($this, $gymId);
            }
        };

        $this->app->bind(function () use ($seeder): UserRoleAssignmentSeeder {
            return $seeder;
        });

        Artisan::call('db:seed', [
            '--class' => UserRoleAssignmentSeeder::class,
        ]);

        // Verify users were created for both gyms
        foreach ([$gym1, $gym2] as $gym) {
            $this->assertDatabaseHas('users', [
                'email' => "owner.gym{$gym->id}@gym.com",
            ]);

            $this->assertDatabaseHas('users', [
                'email' => "john.trainer.gym{$gym->id}@gym.com",
            ]);

            $this->assertDatabaseHas('gym_user', [
                'gym_id' => $gym->id,
                'role' => 'gym_owner',
                'active' => true,
            ]);
        }

        // Verify each gym has its own set of tenant roles
        $tenantRoles = ['manager', 'receptionist', 'trainer', 'member'];
        foreach ([$gym1, $gym2] as $gym) {
            foreach ($tenantRoles as $role) {
                $this->assertDatabaseHas('roles', [
                    'name' => $role,
                    'guard_name' => 'web',
                    'gym_id' => $gym->id,
                ]);
            }
        }
    }

    #[Test]
    public function it_does_not_duplicate_roles_when_run_multiple_times()
    {
        $gym = Gym::query()->create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        $seeder = new class() extends UserRoleAssignmentSeeder
        {
            public function run(): void
            {
                $this->callEnsureRolesExist();
            }

            private function callEnsureRolesExist($gymId = null): void
            {
                $reflection = new ReflectionClass(parent::class);
                $method = $reflection->getMethod('ensureRolesExist');
                $method->setAccessible(true);
                $method->invoke($this, $gymId);
            }
        };

        $this->app->bind(function () use ($seeder): UserRoleAssignmentSeeder {
            return $seeder;
        });

        // Run the seeder twice
        Artisan::call('db:seed', [
            '--class' => UserRoleAssignmentSeeder::class,
        ]);

        Artisan::call('db:seed', [
            '--class' => UserRoleAssignmentSeeder::class,
        ]);

        // Verify only one instance of each role exists
        $this->assertEquals(1, Role::query()->where('name', 'super_admin')->whereNull('gym_id')->count());
        $this->assertEquals(1, Role::query()->where('name', 'gym_owner')->whereNull('gym_id')->count());

        $tenantRoles = ['manager', 'receptionist', 'trainer', 'member'];
        foreach ($tenantRoles as $role) {
            $this->assertEquals(1, Role::query()->where('name', $role)->where('gym_id', $gym->id)->count());
        }
    }

    #[Test]
    public function it_does_not_duplicate_users_when_run_multiple_times()
    {
        $gym = Gym::query()->create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        $seeder = new class() extends UserRoleAssignmentSeeder
        {
            public function run(): void
            {
                $gyms = $this->callGetAvailableGyms();
                if (filled($gyms)) {
                    $gym = $gyms[0];
                    $this->callEnsureRolesExist($gym['id']);
                    $this->callCreateDemoUsers($gym['id']);
                }
            }

            private function callGetAvailableGyms(): array
            {
                $reflection = new ReflectionClass(parent::class);
                $method = $reflection->getMethod('getAvailableGyms');
                $method->setAccessible(true);

                return $method->invoke($this);
            }

            private function callEnsureRolesExist($gymId = null): void
            {
                $reflection = new ReflectionClass(parent::class);
                $method = $reflection->getMethod('ensureRolesExist');
                $method->setAccessible(true);
                $method->invoke($this, $gymId);
            }

            private function callCreateDemoUsers($gymId): void
            {
                $reflection = new ReflectionClass(parent::class);
                $method = $reflection->getMethod('createDemoUsers');
                $method->setAccessible(true);
                $method->invoke($this, $gymId);
            }
        };

        $this->app->bind(function () use ($seeder): UserRoleAssignmentSeeder {
            return $seeder;
        });

        // Run the seeder twice
        Artisan::call('db:seed', [
            '--class' => UserRoleAssignmentSeeder::class,
        ]);

        Artisan::call('db:seed', [
            '--class' => UserRoleAssignmentSeeder::class,
        ]);

        // Verify only one instance of each user exists
        $this->assertEquals(1, User::query()->where('email', "owner.gym{$gym->id}@gym.com")->count());
        $this->assertEquals(1, User::query()->where('email', "john.trainer.gym{$gym->id}@gym.com")->count());
        $this->assertEquals(1, User::query()->where('email', "emma.member.gym{$gym->id}@gmail.com")->count());

        // Verify only one trainer profile exists
        $this->assertEquals(1, Trainer::query()->where('gym_id', $gym->id)->where('name', 'John Trainer')->count());

        // Verify only one member profile exists
        $this->assertEquals(1, GymMember::query()->where('gym_id', $gym->id)->where('name', 'Emma Member')->count());
    }

    #[Test]
    public function it_works_with_no_existing_gyms()
    {
        // Don't create any gyms

        $seeder = new class() extends UserRoleAssignmentSeeder
        {
            public function run(): void
            {
                $this->callEnsureRolesExist(); // Should only create global roles
            }

            private function callEnsureRolesExist($gymId = null): void
            {
                $reflection = new ReflectionClass(parent::class);
                $method = $reflection->getMethod('ensureRolesExist');
                $method->setAccessible(true);
                $method->invoke($this, $gymId);
            }
        };

        $this->app->bind(function () use ($seeder): UserRoleAssignmentSeeder {
            return $seeder;
        });

        Artisan::call('db:seed', [
            '--class' => UserRoleAssignmentSeeder::class,
        ]);

        // Verify global roles were created
        $this->assertDatabaseHas('roles', [
            'name' => 'super_admin',
            'guard_name' => 'web',
            'gym_id' => null,
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'gym_owner',
            'guard_name' => 'web',
            'gym_id' => null,
        ]);

        // Verify no tenant roles were created
        $tenantRoles = ['manager', 'receptionist', 'trainer', 'member'];
        foreach ($tenantRoles as $role) {
            $this->assertEquals(0, Role::query()->where('name', $role)->count());
        }
    }
}
