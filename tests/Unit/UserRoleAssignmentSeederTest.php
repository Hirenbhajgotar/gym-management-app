<?php

namespace Tests\Unit;

use App\Models\Gym;
use App\Models\Trainer;
use App\Models\User;
use Database\Seeders\UserRoleAssignmentSeeder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRoleAssignmentSeederTest extends TestCase
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
            ->with(
                'Select option for:',
                Mockery::type('array'),
                0
            )
            ->andReturn('Do you want to create only super-admin user?');

        $this->mockCommand->shouldReceive('info')->with('🔐 Creating Super-Admin User...');
        $this->mockCommand->shouldReceive('newLine');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin name')->andReturn('Super Admin');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin email')->andReturn('admin@example.com');
        $this->mockCommand->shouldReceive('secret')->with('Enter the super-admin password (minimum 8 characters)')->andReturn('password123');
        $this->mockCommand->shouldReceive('secret')->with('Confirm the password')->andReturn('password123');
        $this->mockCommand->shouldReceive('info')->with("Created global role 'super_admin'");
        $this->mockCommand->shouldReceive('info')->with("✅ Super-admin user 'Super Admin' created successfully!");
        $this->mockCommand->shouldReceive('table');
        $this->mockCommand->shouldReceive('newLine');
        $this->mockCommand->shouldReceive('info')->with('🎉 Super-admin user has been created and is ready to use!');
        $this->mockCommand->shouldReceive('confirm')->with('Do you want to attach super-admin to all gyms in the pivot table?', false)->andReturn(false);
        $this->mockCommand->shouldReceive('info')->with(str_repeat('=', 80));
        $this->mockCommand->shouldReceive('info')->with('📊 FINAL USER ROLE SUMMARY');
        $this->mockCommand->shouldReceive('info')->with('🌍 GLOBAL USERS (Users with Global Roles like super_admin, gym_owner):');
        $this->mockCommand->shouldReceive('info')->with('  🔹 super_admin: 1 users');
        $this->mockCommand->shouldReceive('info')->with('');
        $this->mockCommand->shouldReceive('info')->with(str_repeat('-', 80));
        $this->mockCommand->shouldReceive('info')->with('📈 OVERALL SUMMARY:');
        $this->mockCommand->shouldReceive('info')->with('📈 Total users: 1');
        $this->mockCommand->shouldReceive('info')->with('✅ Users with any Spatie role (global/tenant): 1');
        $this->mockCommand->shouldReceive('info')->with('❌ Users without any Spatie role: 0');
        $this->mockCommand->shouldReceive('info')->with('🏢 Total active gym assignments (pivot entries): 0');

        $this->seeder->run();

        $this->assertDatabaseHas('users', [
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'super_admin',
            'guard_name' => 'web',
            'gym_id' => null,
        ]);
    }

    #[Test]
    public function it_can_run_with_create_demo_users_for_specific_gym_choice()
    {
        // Create a test gym
        $gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        $this->mockCommand->shouldReceive('choice')
            ->once()
            ->with(
                'Select option for:',
                Mockery::type('array'),
                0
            )
            ->andReturn('Do you want to create demo users for specific gym?');

        $this->mockCommand->shouldReceive('info')->with('📝 Creating demo users for specific gym...');
        $this->mockCommand->shouldReceive('choice')
            ->with('Select gym:', ['Test Gym'], 0)
            ->andReturn('Test Gym');
        $this->mockCommand->shouldReceive('info')->with('Creating demo users for gym: Test Gym');

        // Mock all the user creation info messages
        $this->mockCommand->shouldReceive('info')->withArgs(function ($message) {
            return str_contains($message, 'Created role') ||
                str_contains($message, 'Assigned') ||
                str_contains($message, 'Attached') ||
                str_contains($message, 'Updated') ||
                str_contains($message, '✅');
        })->zeroOrMoreTimes();

        // Mock final summary
        $this->mockCommand->shouldReceive('info')->with(str_repeat('=', 80));
        $this->mockCommand->shouldReceive('info')->with('📊 FINAL USER ROLE SUMMARY');
        $this->mockCommand->shouldReceive('info')->with("🏢 GYM: Test Gym (ID: {$gym->id})");
        $this->mockCommand->shouldReceive('info')->withArgs(function ($message) {
            return str_contains($message, '🔹') || str_contains($message, '📈') || str_contains($message, '✅') || str_contains($message, '❌');
        })->zeroOrMoreTimes();
        $this->mockCommand->shouldReceive('info')->with('');
        $this->mockCommand->shouldReceive('info')->with(str_repeat('-', 80));

        $this->seeder->run();

        // Verify demo users were created
        $this->assertDatabaseHas('users', [
            'email' => "owner.gym{$gym->id}@gym.com",
        ]);
        $this->assertDatabaseHas('users', [
            'email' => "alex.manager.gym{$gym->id}@gym.com",
        ]);
    }

    #[Test]
    public function it_can_run_with_invalid_choice()
    {
        $this->mockCommand->shouldReceive('choice')
            ->once()
            ->andReturn('Invalid Choice');

        $this->mockCommand->shouldReceive('fail')
            ->once()
            ->with('Invalid choice selected.');

        $this->mockCommand->shouldReceive('info')->with(str_repeat('=', 80));
        $this->mockCommand->shouldReceive('info')->with('📊 FINAL USER ROLE SUMMARY');
        $this->mockCommand->shouldReceive('info')->with(str_repeat('-', 80));
        $this->mockCommand->shouldReceive('info')->with('📈 OVERALL SUMMARY:');
        $this->mockCommand->shouldReceive('info')->with('📈 Total users: 0');
        $this->mockCommand->shouldReceive('info')->with('✅ Users with any Spatie role (global/tenant): 0');
        $this->mockCommand->shouldReceive('info')->with('❌ Users without any Spatie role: 0');
        $this->mockCommand->shouldReceive('info')->with('🏢 Total active gym assignments (pivot entries): 0');

        $this->seeder->run();
    }

    #[Test]
    public function it_creates_global_roles_correctly()
    {
        $this->mockCommand->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('ensureRolesExist');
        $method->setAccessible(true);

        $method->invoke($this->seeder);

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
    public function it_creates_tenant_roles_for_specific_gym()
    {
        $gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        $this->mockCommand->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('ensureRolesExist');
        $method->setAccessible(true);

        $method->invoke($this->seeder, $gym->id);

        $tenantRoles = ['manager', 'receptionist', 'trainer', 'member'];
        foreach ($tenantRoles as $role) {
            $this->assertDatabaseHas('roles', [
                'name' => $role,
                'guard_name' => 'web',
                'gym_id' => $gym->id,
            ]);
        }
    }

    #[Test]
    public function it_creates_tenant_roles_for_all_gyms()
    {
        $gym1 = Gym::create(['name' => 'Gym 1', 'slug' => 'gym-1']);
        $gym2 = Gym::create(['name' => 'Gym 2', 'slug' => 'gym-2']);

        $this->mockCommand->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('ensureRolesExist');
        $method->setAccessible(true);

        $method->invoke($this->seeder, null);

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
    public function it_validates_super_admin_creation_inputs()
    {
        $this->mockCommand->shouldReceive('info')->with('🔐 Creating Super-Admin User...');
        $this->mockCommand->shouldReceive('newLine');

        // Test empty name
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin name')->andReturn('');
        $this->mockCommand->shouldReceive('fail')->with('Name cannot be empty.');

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('createSuperAdminUser');
        $method->setAccessible(true);

        $method->invoke($this->seeder);
    }

    #[Test]
    public function it_validates_super_admin_email()
    {
        $this->mockCommand->shouldReceive('info')->with('🔐 Creating Super-Admin User...');
        $this->mockCommand->shouldReceive('newLine');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin name')->andReturn('Admin');

        // Test invalid email
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin email')->andReturn('invalid-email');
        $this->mockCommand->shouldReceive('fail')->with('Please enter a valid email address.');

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('createSuperAdminUser');
        $method->setAccessible(true);

        $method->invoke($this->seeder);
    }

    #[Test]
    public function it_validates_existing_email()
    {
        User::create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->mockCommand->shouldReceive('info')->with('🔐 Creating Super-Admin User...');
        $this->mockCommand->shouldReceive('newLine');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin name')->andReturn('Admin');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin email')->andReturn('existing@example.com');
        $this->mockCommand->shouldReceive('fail')->with("User with email 'existing@example.com' already exists.");

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('createSuperAdminUser');
        $method->setAccessible(true);

        $method->invoke($this->seeder);
    }

    #[Test]
    public function it_validates_password_length()
    {
        $this->mockCommand->shouldReceive('info')->with('🔐 Creating Super-Admin User...');
        $this->mockCommand->shouldReceive('newLine');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin name')->andReturn('Admin');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin email')->andReturn('admin@example.com');
        $this->mockCommand->shouldReceive('secret')->with('Enter the super-admin password (minimum 8 characters)')->andReturn('short');
        $this->mockCommand->shouldReceive('fail')->with('Password must be at least 8 characters long. Exiting...');

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('createSuperAdminUser');
        $method->setAccessible(true);

        $method->invoke($this->seeder);
    }

    #[Test]
    public function it_validates_password_confirmation()
    {
        $this->mockCommand->shouldReceive('info')->with('🔐 Creating Super-Admin User...');
        $this->mockCommand->shouldReceive('newLine');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin name')->andReturn('Admin');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin email')->andReturn('admin@example.com');
        $this->mockCommand->shouldReceive('secret')->with('Enter the super-admin password (minimum 8 characters)')->andReturn('password123');
        $this->mockCommand->shouldReceive('secret')->with('Confirm the password')->andReturn('different123');
        $this->mockCommand->shouldReceive('fail')->with('Passwords do not match. Exiting...');

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('createSuperAdminUser');
        $method->setAccessible(true);

        $method->invoke($this->seeder);
    }

    #[Test]
    public function it_creates_demo_users_with_correct_roles()
    {
        $gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        $this->mockCommand->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();

        // Create roles first
        $reflection = new \ReflectionClass($this->seeder);
        $ensureRolesMethod = $reflection->getMethod('ensureRolesExist');
        $ensureRolesMethod->setAccessible(true);
        $ensureRolesMethod->invoke($this->seeder, $gym->id);

        // Create demo users
        $createDemoUsersMethod = $reflection->getMethod('createDemoUsers');
        $createDemoUsersMethod->setAccessible(true);
        $createDemoUsersMethod->invoke($this->seeder, $gym->id);

        // Verify users were created with correct roles
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

        $this->assertDatabaseHas('gym_user', [
            'gym_id' => $gym->id,
            'role' => 'trainer',
            'active' => true,
        ]);
    }

    #[Test]
    public function it_creates_trainer_profiles()
    {
        $gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        $this->mockCommand->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();

        // Create roles first
        $reflection = new \ReflectionClass($this->seeder);
        $ensureRolesMethod = $reflection->getMethod('ensureRolesExist');
        $ensureRolesMethod->setAccessible(true);
        $ensureRolesMethod->invoke($this->seeder, $gym->id);

        // Create demo users
        $createDemoUsersMethod = $reflection->getMethod('createDemoUsers');
        $createDemoUsersMethod->setAccessible(true);
        $createDemoUsersMethod->invoke($this->seeder, $gym->id);

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
        $ensureRolesMethod = $reflection->getMethod('ensureRolesExist');
        $ensureRolesMethod->setAccessible(true);
        $ensureRolesMethod->invoke($this->seeder, $gym->id);

        // Create demo users
        $createDemoUsersMethod = $reflection->getMethod('createDemoUsers');
        $createDemoUsersMethod->setAccessible(true);
        $createDemoUsersMethod->invoke($this->seeder, $gym->id);

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
        $this->mockCommand->shouldReceive('info')->with('📝 Creating demo users for specific gym...');
        $this->mockCommand->shouldReceive('error')->with('No gyms found. Please create gyms first.');

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('createDemoUsersForGym');
        $method->setAccessible(true);

        $method->invoke($this->seeder);
    }

    #[Test]
    public function it_gets_available_gyms()
    {
        $gym1 = Gym::create(['name' => 'Gym 1', 'slug' => 'gym-1']);
        $gym2 = Gym::create(['name' => 'Gym 2', 'slug' => 'gym-2']);

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('getAvailableGyms');
        $method->setAccessible(true);

        $gyms = $method->invoke($this->seeder);

        $this->assertCount(2, $gyms);
        $this->assertEquals('Gym 1', $gyms[0]['name']);
        $this->assertEquals('Gym 2', $gyms[1]['name']);
    }

    #[Test]
    public function it_displays_final_summary_with_users()
    {
        $gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym']);
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // Create a role and assign it
        $role = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        // Create gym_user entry
        DB::table('gym_user')->insert([
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'role' => 'manager',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->mockCommand->shouldReceive('info')->with(str_repeat('=', 80));
        $this->mockCommand->shouldReceive('info')->with('📊 FINAL USER ROLE SUMMARY');
        $this->mockCommand->shouldReceive('info')->with('🌍 GLOBAL USERS (Users with Global Roles like super_admin, gym_owner):');
        $this->mockCommand->shouldReceive('info')->with('  🔹 super_admin: 1 users');
        $this->mockCommand->shouldReceive('info')->with('');
        $this->mockCommand->shouldReceive('info')->with("🏢 GYM: Test Gym (ID: {$gym->id})");
        $this->mockCommand->shouldReceive('info')->with('  🔹 manager: 1 users');
        $this->mockCommand->shouldReceive('info')->with('');
        $this->mockCommand->shouldReceive('info')->with(str_repeat('-', 80));
        $this->mockCommand->shouldReceive('info')->with('📈 OVERALL SUMMARY:');
        $this->mockCommand->shouldReceive('info')->with('📈 Total users: 1');
        $this->mockCommand->shouldReceive('info')->with('✅ Users with any Spatie role (global/tenant): 1');
        $this->mockCommand->shouldReceive('info')->with('❌ Users without any Spatie role: 0');
        $this->mockCommand->shouldReceive('info')->with('🏢 Total active gym assignments (pivot entries): 1');

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('displayFinalSummary');
        $method->setAccessible(true);

        $method->invoke($this->seeder);
    }

    #[Test]
    public function it_handles_exception_during_super_admin_creation()
    {
        $this->mockCommand->shouldReceive('info')->with('🔐 Creating Super-Admin User...');
        $this->mockCommand->shouldReceive('newLine');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin name')->andReturn('Admin');
        $this->mockCommand->shouldReceive('ask')->with('Enter the super-admin email')->andReturn('admin@example.com');
        $this->mockCommand->shouldReceive('secret')->with('Enter the super-admin password (minimum 8 characters)')->andReturn('password123');
        $this->mockCommand->shouldReceive('secret')->with('Confirm the password')->andReturn('password123');
        $this->mockCommand->shouldReceive('info')->with("Created global role 'super_admin'");
        $this->mockCommand->shouldReceive('confirm')->with('Do you want to attach super-admin to all gyms in the pivot table?', false)->andReturn(false);

        // Mock database error
        $this->mockCommand->shouldReceive('error')->with(Mockery::pattern('/Failed to create super-admin user:/'));
        $this->mockCommand->shouldReceive('fail')->with('Please check your database connection and try again.');

        // Force an exception by making the database unavailable
        DB::shouldReceive('beginTransaction')->andThrow(new \Exception('Database connection failed'));

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('createSuperAdminUser');
        $method->setAccessible(true);

        $method->invoke($this->seeder);
    }

    #[Test]
    public function it_creates_or_updates_user_with_global_role()
    {
        $this->mockCommand->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();

        $userData = [
            'name' => 'Test Owner',
            'email' => 'owner@example.com',
            'role' => 'gym_owner',
            'gym_id' => 1,
            'profile_data' => null,
            'is_global_role' => true,
        ];

        // Create global role
        Role::create(['name' => 'gym_owner', 'guard_name' => 'web']);

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('createOrUpdateUser');
        $method->setAccessible(true);

        $user = $method->invoke($this->seeder, $userData);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Test Owner', $user->name);
        $this->assertEquals('owner@example.com', $user->email);
        $this->assertTrue($user->hasRole('gym_owner'));
    }

    #[Test]
    public function it_creates_or_updates_user_with_tenant_role()
    {
        $gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        $this->mockCommand->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();

        $userData = [
            'name' => 'Test Trainer',
            'email' => 'trainer@example.com',
            'role' => 'trainer',
            'gym_id' => $gym->id,
            'profile_data' => ['specialization' => 'Weight Training'],
            'is_global_role' => false,
        ];

        // Create tenant role
        Role::create(['name' => 'trainer', 'guard_name' => 'web', 'gym_id' => $gym->id]);

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('createOrUpdateUser');
        $method->setAccessible(true);

        $user = $method->invoke($this->seeder, $userData);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Test Trainer', $user->name);
        $this->assertEquals('trainer@example.com', $user->email);
        $this->assertTrue($user->hasRole('trainer'));

        // Verify gym_user pivot entry
        $this->assertDatabaseHas('gym_user', [
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'role' => 'trainer',
            'active' => true,
        ]);
    }
}
