<?php

namespace Database\Seeders;

use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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
            'Create only super-admin user',
            'Create demo users for selected gym',
            'Create demo users for all gyms',
            'Assign roles to existing users',
            'Update existing user roles',
        ];

        $selectedChoice = $this->command->choice(
            'Select seeding option:',
            $choices,
            0
        );

        try {
            match ($selectedChoice) {
                'Create only super-admin user' => $this->createSuperAdminUser(),
                'Create demo users for selected gym' => $this->createDemoUsersForGym(),
                'Create demo users for all gyms' => $this->createDemoUsersForAllGyms(),
                'Assign roles to existing users' => $this->assignRolesToExistingUsers(),
                'Update existing user roles' => $this->updateExistingUserRoles(),
                default => $this->command->error('Invalid choice selected.'),
            };
        } catch (\Exception $e) {
            $this->command->error('Error: ' . $e->getMessage());
            $this->command->error('Please check your database connection and try again.');
        } finally {
            $this->displayFinalSummary();
        }
    }

    private function ensureRolesExist($gymId = null): void
    {
        $roles = [
            'super_admin',
            'gym_owner',
            'trainer',
            'receptionist',
            'member',
        ];

        foreach ($roles as $roleName) {
            try {
                // For gym-specific roles
                if ($gymId && $roleName !== 'super_admin') {
                    if (! Role::where('name', $roleName)
                        ->where('guard_name', 'web')
                        ->where('gym_id', $gymId)
                        ->exists()) {
                        Role::create([
                            'name' => $roleName,
                            'guard_name' => 'web',
                            'gym_id' => $gymId,
                        ]);
                        $this->command->info("Created role '{$roleName}' for gym {$gymId}");
                    }
                }
                // For global roles (super_admin)
                elseif ($roleName === 'super_admin') {
                    if (! Role::where('name', $roleName)
                        ->where('guard_name', 'web')
                        ->whereNull('gym_id')
                        ->exists()) {
                        Role::create([
                            'name' => $roleName,
                            'guard_name' => 'web',
                        ]);
                        $this->command->info("Created global role '{$roleName}'");
                    }
                }
            } catch (\Exception $e) {
                $this->command->error("Failed to create role {$roleName}: " . $e->getMessage());
            }
        }
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

        if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
                'email_verified_at' => now(),
            ]);

            // Assign super_admin role (global role)
            $superAdminRole = Role::where('name', 'super_admin')->first();

            if (! $superAdminRole) {
                // Create super_admin role if it doesn't exist
                $superAdminRole = Role::create([
                    'name' => 'super_admin',
                    'guard_name' => 'web',
                ]);

                $this->command->warn('Super-admin role was created as it did not exist.');
            }

            $superAdmin->assignRole($superAdminRole);

            // Optionally, attach super admin to all gyms or keep them global
            if ($this->command->confirm('Do you want to attach super-admin to all gyms?', false)) {
                $gyms = Gym::all();
                foreach ($gyms as $gym) {
                    $superAdmin->gyms()->attach($gym->id, [
                        'role' => 'super_admin',
                        'active' => true,
                    ]);
                }
            }

            $this->command->info("✅ Super-admin user '{$name}' created successfully!");
            $this->command->table(
                ['Field', 'Value'],
                [
                    ['Name', $name],
                    ['Email', $email],
                    ['Role', 'super_admin'],
                    ['Gym Access', 'Global (All Gyms)'],
                    ['Status', 'Active & Verified'],
                    ['Created At', $superAdmin->created_at->format('Y-m-d H:i:s')],
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
     * Create demo users for a specific gym
     */
    private function createDemoUsersForGym(): void
    {
        $this->command->info('📝 Creating demo users for specific gym...');

        // Get available gyms
        $gyms = $this->getAvailableGyms();

        if (empty($gyms)) {
            $this->command->error('No gyms found. Please create gyms first.');

            return;
        }

        $selectedGym = $this->command->choice(
            'Select gym:',
            array_column($gyms, 'name'),
            0
        );

        $gym = collect($gyms)->firstWhere('name', $selectedGym);

        $this->command->info("Creating demo users for gym: {$gym['name']}");
        $this->createDemoUsers($gym['id']);
    }

    /**
     * Create demo users for all gyms
     */
    private function createDemoUsersForAllGyms(): void
    {
        $this->command->info('📝 Creating demo users for all gyms...');

        $gyms = $this->getAvailableGyms();

        if (empty($gyms)) {
            $this->command->fail('No gyms found. Please create gyms first.');

            return;
        }

        foreach ($gyms as $gym) {
            $this->command->info("Creating demo users for gym: {$gym['name']}");
            $this->createDemoUsers($gym['id']);
            $this->command->newLine();
        }
    }

    /**
     * Get available gyms
     */
    private function getAvailableGyms(): array
    {
        if (class_exists(Gym::class)) {
            return Gym::select('id', 'name')->get()->toArray();
        }

        return [];
    }

    /**
     * Create demo users with roles for specific gym
     */
    private function createDemoUsers($gymId): void
    {
        $users = [
            // Gym Owner (one per gym)
            [
                'name' => 'Gym Owner',
                'email' => "owner.gym{$gymId}@gym.com",
                'role' => 'gym_owner',
                'gym_id' => $gymId,
                'profile_data' => null,
            ],

            // Trainer Users
            [
                'name' => 'John Trainer',
                'email' => "john.trainer.gym{$gymId}@gym.com",
                'role' => 'trainer',
                'gym_id' => $gymId,
                'profile_data' => [
                    'specialization' => 'Weight Training, Bodybuilding',
                    'phone' => '+1234567890',
                    'hire_date' => now()->subMonths(12),
                    'salary' => 45000,
                ],
            ],
            [
                'name' => 'Sarah Fitness',
                'email' => "sarah.fitness.gym{$gymId}@gym.com",
                'role' => 'trainer',
                'gym_id' => $gymId,
                'profile_data' => [
                    'specialization' => 'Cardio, Yoga, Pilates',
                    'phone' => '+1234567891',
                    'hire_date' => now()->subMonths(8),
                    'salary' => 42000,
                ],
            ],
            [
                'name' => 'Mike Strong',
                'email' => "mike.strong.gym{$gymId}@gym.com",
                'role' => 'trainer',
                'gym_id' => $gymId,
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
                'email' => "alice.reception.gym{$gymId}@gym.com",
                'role' => 'receptionist',
                'gym_id' => $gymId,
                'profile_data' => [
                    'phone' => '+1234567893',
                    'shift' => 'morning',
                    'hire_date' => now()->subMonths(10),
                    'salary' => 28000,
                ],
            ],
            [
                'name' => 'Bob Front Desk',
                'email' => "bob.frontdesk.gym{$gymId}@gym.com",
                'role' => 'receptionist',
                'gym_id' => $gymId,
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
                'email' => "emma.member.gym{$gymId}@gmail.com",
                'role' => 'member',
                'gym_id' => $gymId,
                'profile_data' => [
                    'phone' => '+1234567895',
                    'join_date' => now()->subMonths(3),
                    'emergency_contact' => 'John Doe - +1234567896',
                ],
            ],
            [
                'name' => 'Tom Member',
                'email' => "tom.member.gym{$gymId}@gmail.com",
                'role' => 'member',
                'gym_id' => $gymId,
                'profile_data' => [
                    'phone' => '+1234567897',
                    'join_date' => now()->subMonths(1),
                    'emergency_contact' => 'Jane Doe - +1234567898',
                ],
            ],
        ];

        foreach ($users as $userData) {
            $user = $this->createOrUpdateUser($userData);
            $this->createProfileForUser($user, $userData['role'], $userData['profile_data'], $userData['gym_id']);
        }
    }

    /**
     * Assign roles to existing users
     */
    private function assignRolesToExistingUsers()
    {
        $this->command->info('👥 Assigning roles to existing users...');

        $gyms = $this->getAvailableGyms();
        $selectedGym = $this->command->choice(
            'Select gym:',
            array_column($gyms, 'name'),
            0
        );
        $gym = collect($gyms)->firstWhere('name', $selectedGym);
        $gymId = $gym['id'];

        // Ensure all required roles exist
        $this->ensureRolesExist($gymId);

        // Get users who don't have roles assigned
        $usersWithoutRoles = User::doesntHave('roles')
            ->whereHas('gyms', function ($query) use ($gymId) {
                $query->where('gyms.id', $gymId); // Assuming 'gyms' is your Gym model's table name
            })->get();

        if ($usersWithoutRoles->isEmpty()) {
            $this->command->info('ℹ️  All users already have roles assigned.');

            return true;
        }

        $roles = Role::where('guard_name', 'web')
            ->where(function ($query) use ($gymId) {
                $query->where('gym_id', $gymId)
                    ->orWhereNull('gym_id'); // Include global roles like super_admin
            })
            ->pluck('name')
            ->toArray();

        if (empty($roles)) {
            $this->command->error("❌ No roles found for gym ID: {$gymId} or globally.");

            return;
        }

        // Add 'skip' option to the roles list
        $roleChoices = array_merge(['skip'], $roles); // Prepend 'skip' to the choices

        $usersProcessed = 0;
        $usersSkipped = 0;
        $usersAssigned = 0;

        foreach ($usersWithoutRoles as $user) {
            $this->command->info('---');
            $this->command->info("User: {$user->name} ({$user->email})");

            // Select role for user
            $selectedOption = $this->command->choice(
                "Select role for {$user->name}:",
                $roleChoices,
                0
            );

            // Check if user chose to skip
            if ($selectedOption === 'skip') {
                $this->command->comment("⏭️ Skipped assigning a role to {$user->name} ({$user->email}) for gym ID: {$gymId}");
                $usersSkipped++;
                $usersProcessed++;

                continue; // Move to the next user
            }

            // If not skipping, proceed with role assignment logic
            $selectedRoleName = $selectedOption;

            // Fetch the specific role based on gym context
            $roleQuery = Role::where('name', $selectedRoleName)
                ->where('guard_name', 'web');

            if ($selectedRoleName === 'super_admin') {
                $roleQuery->whereNull('gym_id'); // Global role
            } else {
                $roleQuery->where('gym_id', $gymId); // Gym-specific role
            }

            $role = $roleQuery->first();

            if (! $role) {
                $this->command->error("Role '{$selectedRoleName}' not found for gym ID: {$gymId}");
                $usersSkipped++; // Consider it skipped due to error
                $usersProcessed++;

                continue;
            }

            setPermissionsTeamId($gymId);
            // Assign role
            $user->assignRole($role);

            // If not super admin, attach to gym(s)
            if ($selectedRoleName !== 'super_admin') {
                if ($gymId) {
                    // Attach to specific gym
                    $user->gyms()->syncWithoutDetaching([$gymId => [
                        'role' => $selectedRoleName,
                        'active' => true,
                    ]]);
                    $this->command->info("✅ Assigned role '{$selectedRoleName}' to {$user->name} for gym ID: {$gymId}");
                }
            } else {
                $this->command->info("✅ Assigned global role '{$selectedRoleName}' to {$user->name}");
            }
        }
    }

    /**
     * Update existing user roles
     */
    private function updateExistingUserRoles()
    {
        $this->command->info('🔄 Updating existing user roles...');

        // Option to work with specific gym or all gyms
        $workWithGym = $this->command->confirm('Do you want to work with a specific gym?', false);

        $gymId = null;
        if ($workWithGym) {
            $gyms = $this->getAvailableGyms();
            $selectedGym = $this->command->choice(
                'Select gym:',
                array_column($gyms, 'name'),
                0
            );
            $gym = collect($gyms)->firstWhere('name', $selectedGym);
            $gymId = $gym['id'];
        }

        $query = User::has('roles')->with(['roles', 'gyms']);
        $usersWithRoles = $query->get();

        if ($usersWithRoles->isEmpty()) {
            $this->command->fail('ℹ️  No users with roles found.');

            return true;
        }

        $roles = Role::pluck('name')->toArray();
        $roles[] = 'Remove all roles';

        foreach ($usersWithRoles as $user) {
            $currentRoles = $user->roles->pluck('name')->implode(', ');
            $gymRoles = $user->gyms->map(function ($gym) {
                return "Gym {$gym->name}: {$gym->pivot->role}";
            })->implode(', ');

            $this->command->info("User: {$user->name} ({$user->email})");
            $this->command->info("  Current roles: {$currentRoles}");
            $this->command->info("  Gym roles: {$gymRoles}");

            if ($this->command->confirm("Update roles for {$user->name}?", false)) {
                $selectedRole = $this->command->choice(
                    "Select new role for {$user->name}:",
                    $roles
                );

                if ($selectedRole === 'Remove all roles') {
                    $user->syncRoles([]);
                    $user->gyms()->detach();
                    $this->command->info("✅ Removed all roles from {$user->name}");
                } else {
                    $user->syncRoles([$selectedRole]);

                    // Update gym roles
                    if ($selectedRole !== 'super_admin') {
                        if ($gymId) {
                            $user->gyms()->syncWithoutDetaching([$gymId => [
                                'role' => $selectedRole,
                                'active' => true,
                            ]]);
                        } else {
                            // Update all gym relationships with new role
                            $gymIds = $user->gyms->pluck('id')->toArray();
                            $syncData = [];
                            foreach ($gymIds as $id) {
                                $syncData[$id] = [
                                    'role' => $selectedRole,
                                    'active' => true,
                                ];
                            }
                            $user->gyms()->sync($syncData);
                        }
                    }

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
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        // Assign role
        if (! $user->hasRole($userData['role'])) {
            $user->assignRole($userData['role']);
        }

        // Attach to gym with role in pivot table
        if (! $user->gyms()->where('gym_id', $userData['gym_id'])->exists()) {
            $user->gyms()->attach($userData['gym_id'], [
                'role' => $userData['role'],
                'active' => true,
            ]);
        } else {
            // Update existing relationship
            $user->gyms()->updateExistingPivot($userData['gym_id'], [
                'role' => $userData['role'],
                'active' => true,
            ]);
        }

        $action = $user->wasRecentlyCreated ? 'Created' : 'Updated';
        $this->command->info("✅ {$action} user: {$user->email} with role: {$userData['role']} for gym ID: {$userData['gym_id']}");

        return $user;
    }

    /**
     * Create profile for user based on role
     */
    private function createProfileForUser(User $user, string $role, ?array $profileData, int $gymId): void
    {
        if (! $profileData) {
            return;
        }

        switch ($role) {
            case 'trainer':
                if (class_exists(Trainer::class)) {
                    Trainer::firstOrCreate(
                        ['user_id' => $user->id, 'gym_id' => $gymId],
                        array_merge([
                            'user_id' => $user->id,
                            'gym_id' => $gymId,
                            'name' => $user->name,
                            'email' => $user->email,
                            'status' => 'active',
                        ], $profileData)
                    );
                }
                break;

            case 'receptionist':
                if (class_exists(User::class)) {
                    $user = User::firstOrCreate(
                        ['id' => $user->id],
                        array_merge([
                            'id' => $user->id,
                            // 'gym_id' => $gymId,
                            'name' => $user->name,
                            'email' => $user->email,
                            'status' => 'active',
                        ], $profileData)
                    );

                    $user->gyms()->attach($gymId, [
                        'role' => 'receptionist',
                        'active' => true,
                    ]);
                }
                break;

            case 'member':
                if (class_exists(GymMember::class)) {
                    GymMember::firstOrCreate(
                        ['user_id' => $user->id, 'gym_id' => $gymId],
                        array_merge([
                            'user_id' => $user->id,
                            'gym_id' => $gymId,
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
        $this->command->info("\n" . str_repeat('=', 80));
        $this->command->info('📊 FINAL USER ROLE SUMMARY (GYM-WISE)');
        $this->command->info(str_repeat('=', 80));

        $gyms = $this->getAvailableGyms();

        // Global users summary (users with roles but no gym assignment)
        $globalUsers = User::has('roles')->doesntHave('gyms')->with('roles')->get();
        if ($globalUsers->isNotEmpty()) {
            $this->command->info('🌍 GLOBAL USERS:');
            $globalRoleCounts = [];
            foreach ($globalUsers as $user) {
                foreach ($user->roles as $role) {
                    $globalRoleCounts[$role->name] = ($globalRoleCounts[$role->name] ?? 0) + 1;
                }
            }
            foreach ($globalRoleCounts as $roleName => $count) {
                $this->command->info("  🔹 {$roleName}: {$count} users");
            }
            $this->command->info('');
        }

        // Gym-wise summary
        foreach ($gyms as $gym) {
            $this->command->info("🏢 GYM: {$gym['name']} (ID: {$gym['id']})");

            // Get users for this gym from pivot table
            $gymUsers = DB::table('gym_user')
                ->join('users', 'gym_user.user_id', '=', 'users.id')
                ->where('gym_user.gym_id', $gym['id'])
                ->where('gym_user.active', true)
                ->select('gym_user.role', 'users.name', 'users.email')
                ->get();

            $roleCounts = [];
            foreach ($gymUsers as $gymUser) {
                $roleCounts[$gymUser->role] = ($roleCounts[$gymUser->role] ?? 0) + 1;
            }

            if (! empty($roleCounts)) {
                foreach ($roleCounts as $roleName => $count) {
                    $this->command->info("  🔹 {$roleName}: {$count} users");
                }
            } else {
                $this->command->info('  ℹ️  No users assigned to this gym');
            }
            $this->command->info('');
        }

        $totalUsers = User::count();
        $usersWithRoles = User::has('roles')->count();
        $usersWithoutRoles = $totalUsers - $usersWithRoles;
        $totalGymAssignments = DB::table('gym_user')->where('active', true)->count();

        $this->command->info(str_repeat('-', 80));
        $this->command->info('📈 OVERALL SUMMARY:');
        $this->command->info("📈 Total users: {$totalUsers}");
        $this->command->info("✅ Users with roles: {$usersWithRoles}");
        $this->command->info("❌ Users without roles: {$usersWithoutRoles}");
        $this->command->info("🏢 Total gym assignments: {$totalGymAssignments}");

        if ($usersWithoutRoles > 0) {
            $this->command->warn("⚠️  {$usersWithoutRoles} users don't have roles assigned!");
        }

        $this->command->info(str_repeat('=', 80));
    }
}
