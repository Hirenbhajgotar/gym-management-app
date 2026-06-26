<?php

namespace Tests\Unit;

use App\Enums\DefaultRole;
use App\Models\Gym;
use App\Models\User;
use Database\Seeders\UserRoleAssignmentSeeder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserRoleAssignmentSeederOptimizedTest extends TestCase
{
    use RefreshDatabase;

    protected UserRoleAssignmentSeeder $seeder;

    protected $mockCommand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seeder = new UserRoleAssignmentSeeder;
        $this->mockCommand = Mockery::mock(Command::class);

        // Use reflection to set the protected command property
        $reflection = new \ReflectionClass($this->seeder);
        $commandProperty = $reflection->getProperty('command');
        $commandProperty->setAccessible(true);
        $commandProperty->setValue($this->seeder, $this->mockCommand);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_can_run_with_create_super_admin_choice()
    {
        $this->mockCommand->shouldReceive('choice')
            ->once()
            ->with('Select seeding option:', Mockery::type('array'), 0)
            ->andReturn('Create only super-admin user');

        $this->mockCommand->shouldReceive('info')->with('🔐 Creating Super-Admin User...');
        $this->mockCommand->shouldReceive('newLine');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin name')->andReturn('Super Admin');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin email')->andReturn('admin@example.com');
        $this->mockCommand->shouldReceive('secret')->with('Enter the super-admin password (minimum 8 characters)')->andReturn('password123');
        $this->mockCommand->shouldReceive('secret')->with('Confirm the password')->andReturn('password123');
        $this->mockCommand->shouldReceive('info')->with("Created global role 'super_admin'");
        $this->mockCommand->shouldReceive('info')->with("Created global role 'admin'");
        $this->mockCommand->shouldReceive('info')->with("Created global role 'gym_owner'");
        $this->mockCommand->shouldReceive('info')->with("Assigned global role 'super_admin' to admin@example.com");
        $this->mockCommand->shouldReceive('confirm')->with('Attach super-admin to all gyms?', false)->andReturn(false);
        $this->mockCommand->shouldReceive('info')->with("✅ Super-admin user 'Super Admin' created successfully!");
        $this->mockCommand->shouldReceive('table');
        $this->mockCommand->shouldReceive('newLine');
        $this->mockCommand->shouldReceive('info')->with('🎉 Super-admin user is ready to use!');

        // Mock final summary
        $this->mockFinalSummary();

        $this->seeder->run();

        $this->assertDatabaseHas('users', [
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => DefaultRole::SUPER_ADMIN->value,
            'guard_name' => 'web',
            'gym_id' => null,
        ]);

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertTrue($user->hasRole(DefaultRole::SUPER_ADMIN->value));
    }

    #[Test]
    public function it_creates_demo_users_for_selected_gym()
    {
        $gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        $this->mockCommand->shouldReceive('choice')
            ->once()
            ->with('Select seeding option:', Mockery::type('array'), 0)
            ->andReturn('Create demo users for selected gym');

        $this->mockCommand->shouldReceive('info')->with('📝 Creating demo users for selected gym...');
        $this->mockCommand->shouldReceive('choice')
            ->with('Select gym:', ['Test Gym'], 0)
            ->andReturn('Test Gym');

        // Mock all the user creation info messages
        $this->mockCommand->shouldReceive('info')->withArgs(function ($message) {
            return str_contains($message, 'Created') ||
                str_contains($message, 'Assigned') ||
                str_contains($message, 'Attached') ||
                str_contains($message, '✅');
        })->zeroOrMoreTimes();

        $this->mockFinalSummary();

        $this->seeder->run();

        // Verify demo users were created with correct roles using DefaultRole enum
        $this->assertDatabaseHas('users', [
            'email' => "owner.gym{$gym->id}@gym.com",
        ]);
        $this->assertDatabaseHas('users', [
            'email' => "alex.manager.gym{$gym->id}@gym.com",
        ]);
        $this->assertDatabaseHas('users', [
            'email' => "john.trainer.gym{$gym->id}@gym.com",
        ]);
        $this->assertDatabaseHas('users', [
            'email' => "emma.member.gym{$gym->id}@gmail.com",
        ]);

        // Verify roles were created using enum values
        $this->assertDatabaseHas('roles', [
            'name' => DefaultRole::GYM_OWNER->value,
            'guard_name' => 'web',
            'gym_id' => null, // Global role
        ]);
        $this->assertDatabaseHas('roles', [
            'name' => DefaultRole::MANAGER->value,
            'guard_name' => 'web',
            'gym_id' => $gym->id, // Tenant role
        ]);
        $this->assertDatabaseHas('roles', [
            'name' => DefaultRole::TRAINER->value,
            'guard_name' => 'web',
            'gym_id' => $gym->id,
        ]);
        $this->assertDatabaseHas('roles', [
            'name' => DefaultRole::GYM_MEMBER->value,
            'guard_name' => 'web',
            'gym_id' => $gym->id,
        ]);
    }

    #[Test]
    public function it_creates_demo_users_for_all_gyms()
    {
        $gym1 = Gym::create(['name' => 'Gym 1', 'slug' => 'gym-1']);
        $gym2 = Gym::create(['name' => 'Gym 2', 'slug' => 'gym-2']);

        $this->mockCommand->shouldReceive('choice')
            ->once()
            ->with('Select seeding option:', Mockery::type('array'), 0)
            ->andReturn('Create demo users for all gyms');

        $this->mockCommand->shouldReceive('info')->with('📝 Creating demo users for all gyms...');
        $this->mockCommand->shouldReceive('info')->with('Creating demo users for gym: Gym 1');
        $this->mockCommand->shouldReceive('info')->with('Creating demo users for gym: Gym 2');
        $this->mockCommand->shouldReceive('newLine')->times(2);

        // Mock user creation messages
        $this->mockCommand->shouldReceive('info')->withArgs(function ($message) {
            return str_contains($message, 'Created') ||
                str_contains($message, 'Assigned') ||
                str_contains($message, 'Attached') ||
                str_contains($message, '✅');
        })->zeroOrMoreTimes();

        $this->mockFinalSummary();

        $this->seeder->run();

        // Verify users were created for both gyms
        foreach ([$gym1, $gym2] as $gym) {
            $this->assertDatabaseHas('users', [
                'email' => "owner.gym{$gym->id}@gym.com",
            ]);
            $this->assertDatabaseHas('users', [
                'email' => "john.trainer.gym{$gym->id}@gym.com",
            ]);
        }
    }

    #[Test]
    public function it_validates_super_admin_inputs()
    {
        $this->mockCommand->shouldReceive('choice')
            ->once()
            ->with('Select seeding option:', Mockery::type('array'), 0)
            ->andReturn('Create only super-admin user');

        $this->mockCommand->shouldReceive('info')->with('🔐 Creating Super-Admin User...');
        $this->mockCommand->shouldReceive('newLine');

        // Test empty name
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin name')->andReturn('');
        $this->mockCommand->shouldReceive('error')->with('Name cannot be empty.');

        $this->mockFinalSummary();

        $this->seeder->run();
    }

    #[Test]
    public function it_validates_super_admin_email()
    {
        $this->mockCommand->shouldReceive('choice')
            ->once()
            ->with('Select seeding option:', Mockery::type('array'), 0)
            ->andReturn('Create only super-admin user');

        $this->mockCommand->shouldReceive('info')->with('🔐 Creating Super-Admin User...');
        $this->mockCommand->shouldReceive('newLine');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin name')->andReturn('Admin');

        // Test invalid email
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin email')->andReturn('invalid-email');
        $this->mockCommand->shouldReceive('error')->with('Please enter a valid email address.');

        $this->mockFinalSummary();

        $this->seeder->run();
    }

    #[Test]
    public function it_validates_existing_email()
    {
        User::create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->mockCommand->shouldReceive('choice')
            ->once()
            ->with('Select seeding option:', Mockery::type('array'), 0)
            ->andReturn('Create only super-admin user');

        $this->mockCommand->shouldReceive('info')->with('🔐 Creating Super-Admin User...');
        $this->mockCommand->shouldReceive('newLine');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin name')->andReturn('Admin');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin email')->andReturn('existing@example.com');
        $this->mockCommand->shouldReceive('error')->with("User with email 'existing@example.com' already exists.");

        $this->mockFinalSummary();

        $this->seeder->run();
    }

    #[Test]
    public function it_validates_password_length()
    {
        $this->mockCommand->shouldReceive('choice')
            ->once()
            ->with('Select seeding option:', Mockery::type('array'), 0)
            ->andReturn('Create only super-admin user');

        $this->mockCommand->shouldReceive('info')->with('🔐 Creating Super-Admin User...');
        $this->mockCommand->shouldReceive('newLine');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin name')->andReturn('Admin');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin email')->andReturn('admin@example.com');
        $this->mockCommand->shouldReceive('secret')->with('Enter the super-admin password (minimum 8 characters)')->andReturn('short');
        $this->mockCommand->shouldReceive('error')->with('Password must be at least 8 characters long.');

        $this->mockFinalSummary();

        $this->seeder->run();
    }

    #[Test]
    public function it_validates_password_confirmation()
    {
        $this->mockCommand->shouldReceive('choice')
            ->once()
            ->with('Select seeding option:', Mockery::type('array'), 0)
            ->andReturn('Create only super-admin user');

        $this->mockCommand->shouldReceive('info')->with('🔐 Creating Super-Admin User...');
        $this->mockCommand->shouldReceive('newLine');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin name')->andReturn('Admin');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin email')->andReturn('admin@example.com');
        $this->mockCommand->shouldReceive('secret')->with('Enter the super-admin password (minimum 8 characters)')->andReturn('password123');
        $this->mockCommand->shouldReceive('secret')->with('Confirm the password')->andReturn('different123');
        $this->mockCommand->shouldReceive('error')->with('Passwords do not match.');

        $this->mockFinalSummary();

        $this->seeder->run();
    }

    #[Test]
    public function it_ensures_global_roles_exist()
    {
        $this->mockCommand->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('ensureGlobalRolesExist');
        $method->setAccessible(true);

        $method->invoke($this->seeder);

        // Verify all global roles from enum were created
        $this->assertDatabaseHas('roles', [
            'name' => DefaultRole::SUPER_ADMIN->value,
            'guard_name' => 'web',
            'gym_id' => null,
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => DefaultRole::ADMIN->value,
            'guard_name' => 'web',
            'gym_id' => null,
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => DefaultRole::GYM_OWNER->value,
            'guard_name' => 'web',
            'gym_id' => null,
        ]);
    }

    #[Test]
    public function it_ensures_tenant_roles_exist_for_specific_gym()
    {
        $gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        $this->mockCommand->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('ensureTenantRolesExist');
        $method->setAccessible(true);

        $method->invoke($this->seeder, $gym->id);

        // Verify tenant roles from enum were created
        foreach (DefaultRole::tenantRoles() as $role) {
            $this->assertDatabaseHas('roles', [
                'name' => $role->value,
                'guard_name' => 'web',
                'gym_id' => $gym->id,
            ]);
        }
    }

    #[Test]
    public function it_ensures_tenant_roles_exist_for_all_gyms()
    {
        $gym1 = Gym::create(['name' => 'Gym 1', 'slug' => 'gym-1']);
        $gym2 = Gym::create(['name' => 'Gym 2', 'slug' => 'gym-2']);

        $this->mockCommand->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('ensureTenantRolesExist');
        $method->setAccessible(true);

        $method->invoke($this->seeder, null);

        // Verify tenant roles were created for both gyms
        foreach ([$gym1, $gym2] as $gym) {
            foreach (DefaultRole::tenantRoles() as $role) {
                $this->assertDatabaseHas('roles', [
                    'name' => $role->value,
                    'guard_name' => 'web',
                    'gym_id' => $gym->id,
                ]);
            }
        }
    }

    #[Test]
    public function it_creates_trainer_profiles()
    {
        $gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        $this->mockCommand->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();

        // Create roles first
        $reflection = new \ReflectionClass($this->seeder);
        $ensureRolesMethod = $reflection->getMethod('ensureTenantRolesExist');
        $ensureRolesMethod->setAccessible(true);
        $ensureRolesMethod->invoke($this->seeder, $gym->id);

        // Create demo users
        $createDemoUsersMethod = $reflection->getMethod('createDemoUsersForGym');
        $createDemoUsersMethod->setAccessible(true);
        $createDemoUsersMethod->invoke($this->seeder, $gym->id, $gym->name);

        // Verify trainer profiles were created
        $this->assertDatabaseHas('trainers', [
            'gym_id' => $gym->id,
            'name' => 'John Trainer',
            'email' => "john.trainer.gym{$gym->id}@gym.com",
        ]);
    }

    #[Test]
    public function it_creates_member_profiles()
    {
        $gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        $this->mockCommand->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();

        // Create roles first
        $reflection = new \ReflectionClass($this->seeder);
        $ensureRolesMethod = $reflection->getMethod('ensureTenantRolesExist');
        $ensureRolesMethod->setAccessible(true);
        $ensureRolesMethod->invoke($this->seeder, $gym->id);

        // Create demo users
        $createDemoUsersMethod = $reflection->getMethod('createDemoUsersForGym');
        $createDemoUsersMethod->setAccessible(true);
        $createDemoUsersMethod->invoke($this->seeder, $gym->id, $gym->name);

        // Verify member profiles were created
        $this->assertDatabaseHas('gym_members', [
            'gym_id' => $gym->id,
            'name' => 'Emma Member',
            'email' => "emma.member.gym{$gym->id}@gmail.com",
        ]);
    }

    #[Test]
    public function it_handles_no_gyms_scenario()
    {
        $this->mockCommand->shouldReceive('choice')
            ->once()
            ->with('Select seeding option:', Mockery::type('array'), 0)
            ->andReturn('Create demo users for selected gym');

        $this->mockCommand->shouldReceive('info')->with('📝 Creating demo users for selected gym...');
        $this->mockCommand->shouldReceive('error')->with('No gyms found. Please create gyms first.');

        $this->mockFinalSummary();

        $this->seeder->run();
    }

    #[Test]
    public function it_assigns_roles_to_existing_users()
    {
        Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        // Create a user without roles
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->mockCommand->shouldReceive('choice')
            ->once()
            ->with('Select seeding option:', Mockery::type('array'), 0)
            ->andReturn('Assign roles to existing users');

        $this->mockCommand->shouldReceive('info')->with('👥 Assigning roles to existing users...');
        $this->mockCommand->shouldReceive('choice')
            ->with('Select context:', ['Global', 'Test Gym'], 0)
            ->andReturn('Global');
        $this->mockCommand->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();
        $this->mockCommand->shouldReceive('choice')
            ->with("Select role for {$user->name} (Context: Global):", [DefaultRole::SUPER_ADMIN->value, DefaultRole::ADMIN->value, DefaultRole::GYM_OWNER->value], 0)
            ->andReturn(DefaultRole::SUPER_ADMIN->value);
        $this->mockCommand->shouldReceive('confirm')
            ->with("Attach {$user->name} to all gyms?", false)
            ->andReturn(false);

        $this->mockFinalSummary();

        $this->seeder->run();

        $this->assertTrue($user->fresh()->hasRole(DefaultRole::SUPER_ADMIN->value));
    }

    #[Test]
    public function it_handles_exception_during_execution()
    {
        $this->mockCommand->shouldReceive('choice')
            ->once()
            ->with('Select seeding option:', Mockery::type('array'), 0)
            ->andReturn('Create only super-admin user');

        $this->mockCommand->shouldReceive('info')->with('🔐 Creating Super-Admin User...');
        $this->mockCommand->shouldReceive('newLine');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin name')->andReturn('Admin');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin email')->andReturn('admin@example.com');
        $this->mockCommand->shouldReceive('secret')->with('Enter the super-admin password (minimum 8 characters)')->andReturn('password123');
        $this->mockCommand->shouldReceive('secret')->with('Confirm the password')->andReturn('password123');

        // Mock database error
        $this->mockCommand->shouldReceive('error')->with('Error: Database connection failed');
        $this->mockCommand->shouldReceive('error')->with('Please check your database connection and try again.');

        $this->mockFinalSummary();

        // Force an exception by making the database unavailable
        DB::shouldReceive('beginTransaction')->andThrow(new \Exception('Database connection failed'));

        $this->seeder->run();
    }

    #[Test]
    public function it_uses_default_role_enum_consistently()
    {
        $gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        $this->mockCommand->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();

        // Create demo users
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('getDemoUsersData');
        $method->setAccessible(true);
        $demoUsers = $method->invoke($this->seeder, $gym->id);

        // Verify all roles use DefaultRole enum
        foreach ($demoUsers as $userData) {
            $this->assertInstanceOf(DefaultRole::class, $userData['role']);
        }

        // Verify specific role mappings
        $roleValues = array_column($demoUsers, 'role');
        $this->assertContains(DefaultRole::GYM_OWNER, $roleValues);
        $this->assertContains(DefaultRole::MANAGER, $roleValues);
        $this->assertContains(DefaultRole::TRAINER, $roleValues);
        $this->assertContains(DefaultRole::RECEPTIONIST, $roleValues);
        $this->assertContains(DefaultRole::GYM_MEMBER, $roleValues);
    }

    /**
     * Mock final summary to avoid repetitive mocking
     */
    private function mockFinalSummary(): void
    {
        $this->mockCommand->shouldReceive('info')->with(str_repeat('=', 80));
        $this->mockCommand->shouldReceive('info')->with('📊 FINAL USER ROLE SUMMARY');
        $this->mockCommand->shouldReceive('info')->with(str_repeat('-', 80));
        $this->mockCommand->shouldReceive('info')->withArgs(function ($message) {
            return str_contains($message, '📈') ||
                str_contains($message, '✅') ||
                str_contains($message, '❌') ||
                str_contains($message, '🏢') ||
                str_contains($message, '🌍') ||
                str_contains($message, '🔹') ||
                str_contains($message, 'ℹ️') ||
                $message === '';
        })->zeroOrMoreTimes();
        $this->mockCommand->shouldReceive('warn')->withAnyArgs()->zeroOrMoreTimes();
    }
}
