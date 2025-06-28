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
        $this->command->info('🚀 Starting Advanced User Role Assignment...');

        // Option 1: Create new users with roles
        if ($this->command->confirm('Do you want to create demo users?', true)) {
            $this->createDemoUsers();
        }

        // Option 2: Assign roles to existing users
        if ($this->command->confirm('Do you want to assign roles to existing users?', false)) {
            $this->assignRolesToExistingUsers();
        }

        // Option 3: Update/reassign roles
        if ($this->command->confirm('Do you want to update existing user roles?', false)) {
            $this->updateExistingUserRoles();
        }

        $this->displayFinalSummary();
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
