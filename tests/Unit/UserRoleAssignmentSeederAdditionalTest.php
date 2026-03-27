<?php

namespace Tests\Unit;

use App\Models\Gym;
use App\Models\Trainer;
use App\Models\User;
use Database\Seeders\UserRoleAssignmentSeeder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRoleAssignmentSeederAdditionalTest extends TestCase
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

    
    public function it_can_assign_roles_to_existing_users_with_global_context()
    {
        // Create a user without roles
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // Create global role
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->mockCommand->shouldReceive('info')->with('👥 Assigning roles to existing users...');
        $this->mockCommand->shouldReceive('choice')
            ->with('Select gym (or choose "Global" to assign global roles):', ['Global'], 0)
            ->andReturn('Global');
        $this->mockCommand->shouldReceive('info')->with("User: {$user->name} ({$user->email})");
        $this->mockCommand->shouldReceive('choice')
            ->with("Select role for {$user->name} (Context: Global):", ['super_admin'], 0)
            ->andReturn('super_admin');
        $this->mockCommand->shouldReceive('info')->with("✅ Assigned global role 'super_admin' to {$user->name}");
        $this->mockCommand->shouldReceive('confirm')
            ->with("Attach {$user->name} to all gyms in the pivot table as 'super_admin'?", false)
            ->andReturn(false);

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('assignRolesToExistingUsers');
        $method->setAccessible(true);

        $method->invoke($this->seeder);

        $this->assertTrue($user->fresh()->hasRole('super_admin'));
    }

    
    public function it_can_assign_roles_to_existing_users_with_gym_context()
    {
        $gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        // Create a user without roles
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // Create tenant role
        Role::create(['name' => 'trainer', 'guard_name' => 'web', 'gym_id' => $gym->id]);

        $this->mockCommand->shouldReceive('info')->with('👥 Assigning roles to existing users...');
        $this->mockCommand->shouldReceive('choice')
            ->with('Select gym (or choose "Global" to assign global roles):', ['Global', 'Test Gym'], 0)
            ->andReturn('Test Gym');
        $this->mockCommand->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();
        $this->mockCommand->shouldReceive('choice')
            ->with("Select role for {$user->name} (Context: Gym ID {$gym->id}):", ['trainer'], 0)
            ->andReturn('trainer');

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('assignRolesToExistingUsers');
        $method->setAccessible(true);

        $method->invoke($this->seeder);

        $this->assertTrue($user->fresh()->hasRole('trainer'));
        $this->assertDatabaseHas('gym_user', [
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'role' => 'trainer',
            'active' => true,
        ]);
    }

    
    public function it_handles_no_users_without_roles_scenario()
    {
        // Create a user with a role
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $role = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        $this->mockCommand->shouldReceive('info')->with('👥 Assigning roles to existing users...');
        $this->mockCommand->shouldReceive('choice')
            ->with('Select gym (or choose "Global" to assign global roles):', ['Global'], 0)
            ->andReturn('Global');
        $this->mockCommand->shouldReceive('info')->with('ℹ️  No users found without the selected type of role assignment.');

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('assignRolesToExistingUsers');
        $method->setAccessible(true);

        $result = $method->invoke($this->seeder);
        $this->assertTrue($result);
    }

    
    public function it_can_update_existing_user_roles_global_context()
    {
        // Create a user with a global role
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $oldRole = Role::create(['name' => 'gym_owner', 'guard_name' => 'web']);
        $newRole = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->assignRole($oldRole);

        $this->mockCommand->shouldReceive('info')->with('🔄 Updating existing user roles...');
        $this->mockCommand->shouldReceive('choice')
            ->with('Select context:', ['Global Roles', 'Specific Gym Roles'], 0)
            ->andReturn('Global Roles');
        $this->mockCommand->shouldReceive('info')->with("User: {$user->name} ({$user->email})");
        $this->mockCommand->shouldReceive('info')->with('  Current Global Roles: gym_owner');
        $this->mockCommand->shouldReceive('info')->with('  Associated Tenant Roles: None');
        $this->mockCommand->shouldReceive('confirm')
            ->with("Update roles for {$user->name} in the selected context?", false)
            ->andReturn(true);
        $this->mockCommand->shouldReceive('choice')
            ->with("Select action/role for {$user->name} (Context: Global):", ['Remove all roles in context', 'gym_owner', 'super_admin'])
            ->andReturn('super_admin');
        $this->mockCommand->shouldReceive('info')->with("✅ Updated global role to 'super_admin' for {$user->name}");
        $this->mockCommand->shouldReceive('confirm')
            ->with("Attach {$user->name} to all gyms in the pivot table as 'super_admin'?", false)
            ->andReturn(false);

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('updateExistingUserRoles');
        $method->setAccessible(true);

        $method->invoke($this->seeder);

        $this->assertFalse($user->fresh()->hasRole('gym_owner'));
        $this->assertTrue($user->fresh()->hasRole('super_admin'));
    }

    
    public function it_can_remove_all_global_roles()
    {
        // Create a user with a global role
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $role = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        $this->mockCommand->shouldReceive('info')->with('🔄 Updating existing user roles...');
        $this->mockCommand->shouldReceive('choice')
            ->with('Select context:', ['Global Roles', 'Specific Gym Roles'], 0)
            ->andReturn('Global Roles');
        $this->mockCommand->shouldReceive('info')->with("User: {$user->name} ({$user->email})");
        $this->mockCommand->shouldReceive('info')->with('  Current Global Roles: super_admin');
        $this->mockCommand->shouldReceive('info')->with('  Associated Tenant Roles: None');
        $this->mockCommand->shouldReceive('confirm')
            ->with("Update roles for {$user->name} in the selected context?", false)
            ->andReturn(true);
        $this->mockCommand->shouldReceive('choice')
            ->with("Select action/role for {$user->name} (Context: Global):", ['Remove all roles in context', 'super_admin'])
            ->andReturn('Remove all roles in context');
        $this->mockCommand->shouldReceive('info')->with("✅ Removed global roles from {$user->name}");

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('updateExistingUserRoles');
        $method->setAccessible(true);

        $method->invoke($this->seeder);

        $this->assertFalse($user->fresh()->hasRole('super_admin'));
    }

    
    public function it_can_update_tenant_roles_for_specific_gym()
    {
        $gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        // Create a user with a tenant role
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $oldRole = Role::create(['name' => 'trainer', 'guard_name' => 'web', 'gym_id' => $gym->id]);
        $newRole = Role::create(['name' => 'manager', 'guard_name' => 'web', 'gym_id' => $gym->id]);
        $user->assignRole($oldRole);

        // Add to gym_user pivot
        $user->gyms()->attach($gym->id, ['role' => 'trainer', 'active' => true]);

        $this->mockCommand->shouldReceive('info')->with('🔄 Updating existing user roles...');
        $this->mockCommand->shouldReceive('choice')
            ->with('Select context:', ['Global Roles', 'Specific Gym Roles'], 0)
            ->andReturn('Specific Gym Roles');
        $this->mockCommand->shouldReceive('choice')
            ->with('Select gym:', ['Test Gym'], 0)
            ->andReturn('Test Gym');
        $this->mockCommand->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();
        $this->mockCommand->shouldReceive('confirm')
            ->with("Update roles for {$user->name} in the selected context?", false)
            ->andReturn(true);
        $this->mockCommand->shouldReceive('choice')
            ->with("Select action/role for {$user->name} (Context: Gym ID {$gym->id}):", ['Remove all roles in context', 'trainer', 'manager'])
            ->andReturn('manager');

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('updateExistingUserRoles');
        $method->setAccessible(true);

        $method->invoke($this->seeder);

        $this->assertFalse($user->fresh()->hasRole('trainer'));
        $this->assertTrue($user->fresh()->hasRole('manager'));
        $this->assertDatabaseHas('gym_user', [
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'role' => 'manager',
            'active' => true,
        ]);
    }

    
    public function it_can_remove_tenant_roles_for_specific_gym()
    {
        $gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        // Create a user with a tenant role
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $role = Role::create(['name' => 'trainer', 'guard_name' => 'web', 'gym_id' => $gym->id]);
        $user->assignRole($role);

        // Add to gym_user pivot
        $user->gyms()->attach($gym->id, ['role' => 'trainer', 'active' => true]);

        $this->mockCommand->shouldReceive('info')->with('🔄 Updating existing user roles...');
        $this->mockCommand->shouldReceive('choice')
            ->with('Select context:', ['Global Roles', 'Specific Gym Roles'], 0)
            ->andReturn('Specific Gym Roles');
        $this->mockCommand->shouldReceive('choice')
            ->with('Select gym:', ['Test Gym'], 0)
            ->andReturn('Test Gym');
        $this->mockCommand->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();
        $this->mockCommand->shouldReceive('confirm')
            ->with("Update roles for {$user->name} in the selected context?", false)
            ->andReturn(true);
        $this->mockCommand->shouldReceive('choice')
            ->with("Select action/role for {$user->name} (Context: Gym ID {$gym->id}):", ['Remove all roles in context', 'trainer'])
            ->andReturn('Remove all roles in context');

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('updateExistingUserRoles');
        $method->setAccessible(true);

        $method->invoke($this->seeder);

        $this->assertFalse($user->fresh()->hasRole('trainer'));
        $this->assertDatabaseMissing('gym_user', [
            'user_id' => $user->id,
            'gym_id' => $gym->id,
        ]);
    }

    
    public function it_handles_no_users_with_roles_scenario()
    {
        $this->mockCommand->shouldReceive('info')->with('🔄 Updating existing user roles...');
        $this->mockCommand->shouldReceive('choice')
            ->with('Select context:', ['Global Roles', 'Specific Gym Roles'], 0)
            ->andReturn('Global Roles');
        $this->mockCommand->shouldReceive('fail')->with('ℹ️  No users with roles found.');

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('updateExistingUserRoles');
        $method->setAccessible(true);

        $result = $method->invoke($this->seeder);
        $this->assertTrue($result);
    }

    
    public function it_creates_demo_users_for_all_gyms()
    {
        $gym1 = Gym::create(['name' => 'Gym 1', 'slug' => 'gym-1']);
        $gym2 = Gym::create(['name' => 'Gym 2', 'slug' => 'gym-2']);

        $this->mockCommand->shouldReceive('info')->with('📝 Creating demo users for all gyms...');
        $this->mockCommand->shouldReceive('info')->with('Creating demo users for gym: Gym 1');
        $this->mockCommand->shouldReceive('info')->with('Creating demo users for gym: Gym 2');
        $this->mockCommand->shouldReceive('newLine')->times(2);
        $this->mockCommand->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('createDemoUsersForAllGyms');
        $method->setAccessible(true);

        $method->invoke($this->seeder);

        // Verify users were created for both gyms
        $this->assertDatabaseHas('users', [
            'email' => "owner.gym{$gym1->id}@gym.com",
        ]);
        $this->assertDatabaseHas('users', [
            'email' => "owner.gym{$gym2->id}@gym.com",
        ]);
    }

    
    public function it_handles_no_gyms_for_all_gyms_scenario()
    {
        $this->mockCommand->shouldReceive('info')->with('📝 Creating demo users for all gyms...');
        $this->mockCommand->shouldReceive('fail')->with('No gyms found. Please create gyms first.');

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('createDemoUsersForAllGyms');
        $method->setAccessible(true);

        $method->invoke($this->seeder);
    }

    
    public function it_updates_existing_user_instead_of_creating_new_one()
    {
        $gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        // Create existing user
        $existingUser = User::create([
            'name' => 'Old Name',
            'email' => 'john.trainer.gym1@gym.com',
            'password' => Hash::make('oldpassword'),
        ]);

        $this->mockCommand->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();

        // Create roles first
        $reflection = new \ReflectionClass($this->seeder);
        $ensureRolesMethod = $reflection->getMethod('ensureRolesExist');
        $ensureRolesMethod->setAccessible(true);
        $ensureRolesMethod->invoke($this->seeder, $gym->id);

        $userData = [
            'name' => 'John Trainer',
            'email' => 'john.trainer.gym1@gym.com', // Same email as existing user
            'role' => 'trainer',
            'gym_id' => $gym->id,
            'profile_data' => ['specialization' => 'Weight Training'],
            'is_global_role' => false,
        ];

        // Create or update user
        $createOrUpdateMethod = $reflection->getMethod('createOrUpdateUser');
        $createOrUpdateMethod->setAccessible(true);
        $user = $createOrUpdateMethod->invoke($this->seeder, $userData);

        // Should be the same user (updated, not created)
        $this->assertEquals($existingUser->id, $user->id);
        $this->assertEquals('Old Name', $user->name); // Name doesn't get updated in firstOrCreate
        $this->assertEquals('john.trainer.gym1@gym.com', $user->email);
        $this->assertFalse($user->wasRecentlyCreated);
    }

    
    public function it_skips_profile_creation_when_no_profile_data()
    {
        $gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym']);
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('createProfileForUser');
        $method->setAccessible(true);

        // Should not create any profile when profile_data is null
        $method->invoke($this->seeder, $user, 'trainer', null, $gym->id);

        // Verify no trainer profile was created
        $this->assertDatabaseMissing('trainers', [
            'user_id' => $user->id,
            'gym_id' => $gym->id,
        ]);
    }

    
    public function it_handles_roles_that_dont_exist_when_assigning()
    {
        $this->mockCommand->shouldReceive('info')->with('👥 Assigning roles to existing users...');
        $this->mockCommand->shouldReceive('choice')
            ->with('Select gym (or choose "Global" to assign global roles):', ['Global'], 0)
            ->andReturn('Global');
        $this->mockCommand->shouldReceive('error')->with('No roles available for selection in the chosen context.');

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('assignRolesToExistingUsers');
        $method->setAccessible(true);

        $method->invoke($this->seeder);
    }

    
    public function it_displays_summary_with_no_global_users()
    {
        $gym = Gym::create(['name' => 'Test Gym', 'slug' => 'test-gym']);

        $this->mockCommand->shouldReceive('info')->with(str_repeat('=', 80));
        $this->mockCommand->shouldReceive('info')->with('📊 FINAL USER ROLE SUMMARY');
        $this->mockCommand->shouldReceive('info')->with("🏢 GYM: Test Gym (ID: {$gym->id})");
        $this->mockCommand->shouldReceive('info')->with('  ℹ️  No users assigned to this gym via pivot table');
        $this->mockCommand->shouldReceive('info')->with('');
        $this->mockCommand->shouldReceive('info')->with(str_repeat('-', 80));
        $this->mockCommand->shouldReceive('info')->with('📈 OVERALL SUMMARY:');
        $this->mockCommand->shouldReceive('info')->with('📈 Total users: 0');
        $this->mockCommand->shouldReceive('info')->with('✅ Users with any Spatie role (global/tenant): 0');
        $this->mockCommand->shouldReceive('info')->with('❌ Users without any Spatie role: 0');
        $this->mockCommand->shouldReceive('info')->with('🏢 Total active gym assignments (pivot entries): 0');

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('displayFinalSummary');
        $method->setAccessible(true);

        $method->invoke($this->seeder);
    }

    
    public function it_displays_warning_for_users_without_roles()
    {
        // Create a user without any roles
        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->mockCommand->shouldReceive('info')->with(str_repeat('=', 80));
        $this->mockCommand->shouldReceive('info')->with('📊 FINAL USER ROLE SUMMARY');
        $this->mockCommand->shouldReceive('info')->with(str_repeat('-', 80));
        $this->mockCommand->shouldReceive('info')->with('📈 OVERALL SUMMARY:');
        $this->mockCommand->shouldReceive('info')->with('📈 Total users: 1');
        $this->mockCommand->shouldReceive('info')->with('✅ Users with any Spatie role (global/tenant): 0');
        $this->mockCommand->shouldReceive('info')->with('❌ Users without any Spatie role: 1');
        $this->mockCommand->shouldReceive('info')->with('🏢 Total active gym assignments (pivot entries): 0');
        $this->mockCommand->shouldReceive('warn')->with("⚠️  1 users don't have any Spatie roles assigned!");

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->seeder);
        $method = $reflection->getMethod('displayFinalSummary');
        $method->setAccessible(true);

        $method->invoke($this->seeder);
    }
}
