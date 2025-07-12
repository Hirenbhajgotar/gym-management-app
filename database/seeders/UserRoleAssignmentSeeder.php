<?php

namespace Database\Seeders;

use App\Models\GymMember;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserRoleAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $choices = [
            'Do you want to create only super-admin user?',
            'Do you want to create demo users?',
            'Do you want to assign roles to existing users?',
            'Do you want to update existing user roles?',
        ];

        $selectedChoice = $this->command->choice(
            "Select option for:",
            $choices,
            0
        );

        switch ($selectedChoice) {
            case 'Do you want to create only super-admin user?':
                $this->createSuperAdminUser();
                break;

            case 'Do you want to create demo users?':
                $this->createDemoUsers();
                break;

            case 'Do you want to assign roles to existing users?':
                $this->assignRolesToExistingUsers();
                break;

            case 'Do you want to update existing user roles?':
                $this->updateExistingUserRoles();
                break;

            default:
                $this->command->fail('Invalid choice selected.');
                break;
        }

        $this->displayFinalSummary();
    }

    public function createSuperAdminUser()
    {
        $this->command->info('🔐 Creating Super-Admin User...');
        $this->command->newLine();

        // Prompt for user details
        $name = $this->command->ask('Enter the super-admin name');

        if (empty($name)) {
            $this->command->fail('Name cannot be empty.');
            return;
        }

        $email = $this->command->ask('Enter the super-admin email');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->command->fail('Please enter a valid email address.');
            return;
        }

        // Check if email already exists
        if (User::where('email', $email)->exists()) {
            $this->command->fail("User with email '{$email}' already exists.");
            return;
        }

        $password = $this->command->secret('Enter the super-admin password (minimum 8 characters)');

        if (empty($password) || strlen($password) < 8) {
            $this->command->fail('Password must be at least 8 characters long. Exiting...');
            return;
        }

        $confirmPassword = $this->command->secret('Confirm the password');

        if ($password !== $confirmPassword) {
            $this->command->fail('Passwords do not match. Exiting...');
            return;
        }

        try {
            // Create the super-admin user
            $superAdmin = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(), // Auto-verify super-admin
            ]);

            // Assign super_admin role (assuming you have a super_admin role)
            $superAdminRole = Role::where('name', 'super_admin')->first();

            if (!$superAdminRole) {
                // Create super_admin role if it doesn't exist
                $superAdminRole = Role::create([
                    'name' => 'super_admin',
                    'guard_name' => 'web'
                ]);

                $this->command->warn('Super-admin role was created as it did not exist.');
            }

            $superAdmin->assignRole($superAdminRole);

            $this->command->info("✅ Super-admin user '{$name}' created successfully!");
            $this->command->table(
                ['Field', 'Value'],
                [
                    ['Name', $name],
                    ['Email', $email],
                    ['Role', 'super_admin'],
                    ['Status', 'Active & Verified'],
                    ['Created At', $superAdmin->created_at->format('Y-m-d H:i:s')]
                ]
            );

            $this->command->newLine();
            $this->command->info('🎉 Super-admin user has been created and is ready to use!');
        } catch (\Exception $e) {
            $this->command->error('Failed to create super-admin user: ' . $e->getMessage());
            $this->command->fail('Please check your database connection and try again.');
        }
    }

    /**
     * Create demo users with roles
     */
    private function createDemoUsers(): void
    {
        $this->command->info('📝 Creating demo users...');

        $users = [
            // Admin Users
            [
                'name' => 'Super Administrator',
                'email' => 'superadmin@gym.com',
                'role' => 'super_admin',
                'profile_data' => null,
            ],
            [
                'name' => 'Gym Owner',
                'email' => 'owner@gym.com',
                'role' => 'gym_owner',
                'profile_data' => null,
            ],

            // Trainer Users
            [
                'name' => 'John Trainer',
                'email' => 'john.trainer@gym.com',
                'role' => 'trainer',
                'profile_data' => [
                    'specialization' => 'Weight Training, Bodybuilding',
                    'phone' => '+1234567890',
                    'hire_date' => now()->subMonths(12),
                    'salary' => 45000,
                ],
            ],
            [
                'name' => 'Sarah Fitness',
                'email' => 'sarah.fitness@gym.com',
                'role' => 'trainer',
                'profile_data' => [
                    'specialization' => 'Cardio, Yoga, Pilates',
                    'phone' => '+1234567891',
                    'hire_date' => now()->subMonths(8),
                    'salary' => 42000,
                ],
            ],
            [
                'name' => 'Mike Strong',
                'email' => 'mike.strong@gym.com',
                'role' => 'trainer',
                'profile_data' => [
                    'specialization' => 'CrossFit, Functional Training',
                    'phone' => '+1234567892',
                    'hire_date' => now()->subMonths(6),
                    'salary' => 48000,
                ],
            ],

            // Receptionist Users
            [
                'name' => 'Alice Reception',
                'email' => 'alice.reception@gym.com',
                'role' => 'receptionist',
                'profile_data' => [
                    'phone' => '+1234567893',
                    'shift' => 'morning',
                    'hire_date' => now()->subMonths(10),
                    'salary' => 28000,
                ],
            ],
            [
                'name' => 'Bob Front Desk',
                'email' => 'bob.frontdesk@gym.com',
                'role' => 'receptionist',
                'profile_data' => [
                    'phone' => '+1234567894',
                    'shift' => 'evening',
                    'hire_date' => now()->subMonths(4),
                    'salary' => 25000,
                ],
            ],

            // Member Users
            [
                'name' => 'Emma Member',
                'email' => 'emma.member@gmail.com',
                'role' => 'member',
                'profile_data' => [
                    'phone' => '+1234567895',
                    'join_date' => now()->subMonths(3),
                    'emergency_contact' => 'John Doe - +1234567896',
                ],
            ],
            [
                'name' => 'Tom Member',
                'email' => 'tom.member@gmail.com',
                'role' => 'member',
                'profile_data' => [
                    'phone' => '+1234567897',
                    'join_date' => now()->subMonths(1),
                    'emergency_contact' => 'Jane Doe - +1234567898',
                ],
            ],
        ];

        foreach ($users as $userData) {
            $user = $this->createOrUpdateUser($userData);
            $this->createProfileForUser($user, $userData['role'], $userData['profile_data']);
        }
    }

    /**
     * Assign roles to existing users
     */
    private function assignRolesToExistingUsers(): void
    {
        $this->command->info('👥 Assigning roles to existing users...');

        $usersWithoutRoles = User::doesntHave('roles')->get();

        if ($usersWithoutRoles->isEmpty()) {
            $this->command->info('ℹ️  All users already have roles assigned.');
            return;
        }

        $roles = Role::pluck('name')->toArray();

        foreach ($usersWithoutRoles as $user) {
            $this->command->info("User: {$user->name} ({$user->email})");

            $selectedRole = $this->command->choice(
                "Select role for {$user->name}:",
                $roles,
                0
            );

            $user->assignRole($selectedRole);
            $this->command->info("✅ Assigned role '{$selectedRole}' to {$user->name}");
        }
    }

    /**
     * Update existing user roles
     */
    private function updateExistingUserRoles(): void
    {
        $this->command->info('🔄 Updating existing user roles...');

        $usersWithRoles = User::has('roles')->with('roles')->get();

        if ($usersWithRoles->isEmpty()) {
            $this->command->info('ℹ️  No users with roles found.');
            return;
        }

        $roles = Role::pluck('name')->toArray();
        $roles[] = 'Remove all roles';

        foreach ($usersWithRoles as $user) {
            $currentRoles = $user->roles->pluck('name')->implode(', ');
            $this->command->info("User: {$user->name} ({$user->email}) - Current roles: {$currentRoles}");

            if ($this->command->confirm("Update roles for {$user->name}?", false)) {
                $selectedRole = $this->command->choice(
                    "Select new role for {$user->name}:",
                    $roles
                );

                if ($selectedRole === 'Remove all roles') {
                    $user->syncRoles([]);
                    $this->command->info("✅ Removed all roles from {$user->name}");
                } else {
                    $user->syncRoles([$selectedRole]);
                    $this->command->info("✅ Updated role to '{$selectedRole}' for {$user->name}");
                }
            }
        }
    }

    /**
     * Create or update user
     */
    private function createOrUpdateUser(array $userData): User
    {
        $user = User::firstOrCreate(
            ['email' => $userData['email']],
            [
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Assign role
        if (!$user->hasRole($userData['role'])) {
            $user->assignRole($userData['role']);
        }

        $action = $user->wasRecentlyCreated ? 'Created' : 'Updated';
        $this->command->info("✅ {$action} user: {$user->email} with role: {$userData['role']}");

        return $user;
    }

    /**
     * Create profile for user based on role
     */
    private function createProfileForUser(User $user, string $role, ?array $profileData): void
    {
        if (!$profileData) {
            return;
        }

        switch ($role) {
            case 'trainer':
                if (class_exists(Trainer::class)) {
                    Trainer::firstOrCreate(
                        ['user_id' => $user->id],
                        array_merge([
                            'user_id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'status' => 'active',
                        ], $profileData)
                    );
                }
                break;

            case 'receptionist':
                if (class_exists(User::class)) {
                    User::firstOrCreate(
                        ['user_id' => $user->id],
                        array_merge([
                            'user_id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'status' => 'active',
                        ], $profileData)
                    );
                }
                break;

            case 'member':
                if (class_exists(GymMember::class)) {
                    GymMember::firstOrCreate(
                        ['user_id' => $user->id],
                        array_merge([
                            'user_id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'status' => 'active',
                        ], $profileData)
                    );
                }
                break;
        }
    }

    /**
     * Display final summary
     */
    private function displayFinalSummary(): void
    {
        $this->command->info("\n" . str_repeat('=', 70));
        $this->command->info('📊 FINAL USER ROLE SUMMARY');
        $this->command->info(str_repeat('=', 70));

        $roles = Role::withCount('users')->get();

        foreach ($roles as $role) {
            $this->command->info("🔹 {$role->name}: {$role->users_count} users");
        }

        $totalUsers = User::count();
        $usersWithRoles = User::has('roles')->count();
        $usersWithoutRoles = $totalUsers - $usersWithRoles;

        $this->command->info(str_repeat('-', 70));
        $this->command->info("📈 Total users: {$totalUsers}");
        $this->command->info("✅ Users with roles: {$usersWithRoles}");
        $this->command->info("❌ Users without roles: {$usersWithoutRoles}");

        if ($usersWithoutRoles > 0) {
            $this->command->warn("⚠️  {$usersWithoutRoles} users don't have roles assigned!");
        }

        $this->command->info(str_repeat('=', 70));
    }
}
