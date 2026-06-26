<?php

namespace Database\Seeders;

use App\Enums\DefaultRole;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserRoleAssignmentSeeder extends Seeder
{
    /**
     * Statistics for tracking seeding progress.
     */
    private array $stats = [
        'users_created' => 0,
        'users_updated' => 0,
        'roles_created' => 0,
        'roles_assigned' => 0,
        'gym_attachments' => 0,
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset permission cache
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $choices = [
            'Create only super-admin user',
            'Create demo users for selected gym',
            'Create demo users for all gyms',
            'Assign roles to existing users',
            'Update existing user roles',
            'Bulk assign role to users by email list',
        ];

        $selectedChoice = $this->command->choice(
            'Select seeding option:',
            $choices,
            0,
        );

        try {
            match ($selectedChoice) {
                'Create only super-admin user' => $this->createSuperAdminUser(),
                'Create demo users for selected gym' => $this->createDemoUsersForGym(),
                'Create demo users for all gyms' => $this->createDemoUsersForAllGyms(),
                'Assign roles to existing users' => $this->assignRolesToExistingUsers(),
                'Update existing user roles' => $this->updateExistingUserRoles(),
                'Bulk assign role to users by email list' => $this->bulkAssignRolesByEmail(),
                default => $this->command->error('Invalid choice selected.'),
            };
        } catch (\Exception $e) {
            $this->command->error('Error: ' . $e->getMessage());
            $this->command->error('Please check your database connection and try again.');
        } finally {
            $this->displayFinalSummary();
        }
    }

    /**
     * Ensure all required roles exist for a gym.
     */
    private function ensureRolesExist(?int $gymId = null): void
    {
        foreach (DefaultRole::cases() as $role) {
            $roleName = $role->value;

            try {
                if ($role->isTenantRole() && $gymId) {
                    // Gym-specific role
                    if (! $this->roleExists($roleName, $gymId)) {
                        Role::create([
                            'name' => $roleName,
                            'guard_name' => 'web',
                            'gym_id' => $gymId,
                        ]);
                        $this->stats['roles_created']++;
                        $this->command->info("Created role '{$roleName}' for gym {$gymId}");
                    }
                } elseif (! $role->isTenantRole()) {
                    // Global role (super_admin)
                    if (! $this->roleExists($roleName)) {
                        Role::create([
                            'name' => $roleName,
                            'guard_name' => 'web',
                        ]);
                        $this->stats['roles_created']++;
                        $this->command->info("Created global role '{$roleName}'");
                    }
                }
            } catch (\Exception $e) {
                $this->command->error("Failed to create role {$roleName}: " . $e->getMessage());
            }
        }
    }

    /**
     * Check if a role exists.
     */
    private function roleExists(string $roleName, ?int $gymId = null): bool
    {
        return Role::query()->where('name', $roleName)
            ->where('guard_name', 'web')
            ->when($gymId, fn ($q) => $q->where('gym_id', $gymId), fn ($q) => $q->whereNull('gym_id'))
            ->exists();
    }

    /**
     * Get a specific role by name and gym context.
     */
    private function getRole(string $roleName, ?int $gymId = null): ?Role
    {
        $role = DefaultRole::tryFrom($roleName);

        $query = Role::query()->where('name', $roleName)->where('guard_name', 'web');

        if ($role?->isTenantRole() && $gymId) {
            $query->where('gym_id', $gymId);
        } else {
            $query->whereNull('gym_id');
        }

        return $query->first();
    }

    /**
     * Create the super-admin user.
     */
    public function createSuperAdminUser(): void
    {
        $this->command->info('🔐 Creating Super-Admin User...');
        $this->command->newLine();

        $name = $this->command->ask('Enter the super-admin name');
        if (blank($name)) {
            $this->command->fail('Name cannot be empty.');

            return;
        }

        $email = $this->command->ask('Enter the super-admin email');
        if (blank($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->command->fail('Please enter a valid email address.');

            return;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->command->fail("User with email '{$email}' already exists.");

            return;
        }

        $password = $this->command->secret('Enter the super-admin password (minimum 8 characters)');
        if (blank($password) || strlen($password) < 8) {
            $this->command->fail('Password must be at least 8 characters long.');

            return;
        }

        $confirmPassword = $this->command->secret('Confirm the password');
        if ($password !== $confirmPassword) {
            $this->command->fail('Passwords do not match.');

            return;
        }

        try {
            DB::beginTransaction();

            $superAdmin = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);
            $this->stats['users_created']++;

            // Ensure super_admin role exists and assign it
            $this->ensureRolesExist();
            $superAdminRole = $this->getRole(DefaultRole::SUPER_ADMIN->value);

            if ($superAdminRole) {
                $superAdmin->assignRole($superAdminRole);
                $this->stats['roles_assigned']++;
            }

            // Optionally attach to all gyms
            if ($this->command->confirm('Do you want to attach super-admin to all gyms?', false)) {
                $gyms = Gym::all();
                foreach ($gyms as $gym) {
                    $superAdmin->gyms()->attach($gym->id, [
                        'role' => DefaultRole::SUPER_ADMIN->value,
                        'active' => true,
                    ]);
                    $this->stats['gym_attachments']++;
                }
            }

            DB::commit();

            $this->command->info("✅ Super-admin user '{$name}' created successfully!");
            $this->command->table(
                ['Field', 'Value'],
                [
                    ['Name', $name],
                    ['Email', $email],
                    ['Role', DefaultRole::SUPER_ADMIN->value],
                    ['Status', 'Active & Verified'],
                    ['Created At', $superAdmin->created_at->format('Y-m-d H:i:s')],
                ],
            );
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Failed to create super-admin user: ' . $e->getMessage());
        }
    }

    /**
     * Create demo users for a specific gym.
     */
    private function createDemoUsersForGym(): void
    {
        $this->command->info('📝 Creating demo users for specific gym...');

        $gyms = $this->getAvailableGyms();
        if (blank($gyms)) {
            $this->command->error('No gyms found. Please create gyms first.');

            return;
        }

        $selectedGym = $this->command->choice('Select gym:', array_column($gyms, 'name'), 0);
        $gym = collect($gyms)->firstWhere('name', $selectedGym);

        $this->command->info("Creating demo users for gym: {$gym['name']}");
        $this->createDemoUsers($gym['id']);
    }

    /**
     * Create demo users for all gyms.
     */
    private function createDemoUsersForAllGyms(): void
    {
        $this->command->info('📝 Creating demo users for all gyms...');

        $gyms = $this->getAvailableGyms();
        if (blank($gyms)) {
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
     * Get available gyms.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function getAvailableGyms(): array
    {
        return Gym::query()->select('id', 'name')->get()->toArray();
    }

    /**
     * Create demo users with roles for a specific gym.
     */
    private function createDemoUsers(int $gymId): void
    {
        // Ensure roles exist for this gym
        $this->ensureRolesExist($gymId);

        $demoUsers = $this->getDemoUsersConfig($gymId);

        DB::beginTransaction();
        try {
            foreach ($demoUsers as $userData) {
                $user = $this->createOrUpdateUser($userData);
                $this->createProfileForUser($user, $userData['role'], $userData['profile_data'] ?? null, $gymId);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Failed to create demo users: ' . $e->getMessage());
        }
    }

    /**
     * Get demo users configuration.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getDemoUsersConfig(int $gymId): array
    {
        return [
            [
                'name' => 'Gym Owner',
                'email' => "owner.gym{$gymId}@gym.com",
                'role' => DefaultRole::GYM_OWNER->value,
                'gym_id' => $gymId,
            ],
            [
                'name' => 'John Trainer',
                'email' => "john.trainer.gym{$gymId}@gym.com",
                'role' => DefaultRole::TRAINER->value,
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
                'role' => DefaultRole::TRAINER->value,
                'gym_id' => $gymId,
                'profile_data' => [
                    'specialization' => 'Cardio, Yoga, Pilates',
                    'phone' => '+1234567891',
                    'hire_date' => now()->subMonths(8),
                    'salary' => 42000,
                ],
            ],
            [
                'name' => 'Alice Reception',
                'email' => "alice.reception.gym{$gymId}@gym.com",
                'role' => DefaultRole::RECEPTIONIST->value,
                'gym_id' => $gymId,
                'profile_data' => [
                    'phone' => '+1234567893',
                    'shift' => 'morning',
                    'hire_date' => now()->subMonths(10),
                    'salary' => 28000,
                ],
            ],
            [
                'name' => 'Emma Member',
                'email' => "emma.member.gym{$gymId}@gmail.com",
                'role' => DefaultRole::GYM_MEMBER->value,
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
                'role' => DefaultRole::GYM_MEMBER->value,
                'gym_id' => $gymId,
                'profile_data' => [
                    'phone' => '+1234567897',
                    'join_date' => now()->subMonths(1),
                    'emergency_contact' => 'Jane Doe - +1234567898',
                ],
            ],
        ];
    }

    /**
     * Assign roles to existing users.
     */
    private function assignRolesToExistingUsers(): void
    {
        $this->command->info('👥 Assigning roles to existing users...');

        $gyms = $this->getAvailableGyms();
        if (blank($gyms)) {
            $this->command->error('No gyms found. Please create gyms first.');

            return;
        }

        $selectedGym = $this->command->choice('Select gym:', array_column($gyms, 'name'), 0);
        $gym = collect($gyms)->firstWhere('name', $selectedGym);
        $gymId = $gym['id'];

        $this->ensureRolesExist($gymId);

        // Get users without roles for this gym
        $usersWithoutRoles = User::query()->doesntHave('roles')
            ->whereHas('gyms', fn (Builder $q) => $q->where('gyms.id', $gymId))
            ->get();

        if ($usersWithoutRoles->isEmpty()) {
            $this->command->info('All users already have roles assigned.');

            return;
        }

        $roleChoices = $this->getRoleChoices($gymId);
        $roleChoices = array_merge(['skip'], $roleChoices);

        foreach ($usersWithoutRoles as $user) {
            $this->command->info("---\nUser: {$user->name} ({$user->email})");

            $selectedOption = $this->command->choice("Select role for {$user->name}:", $roleChoices, 0);

            if ($selectedOption === 'skip') {
                $this->command->comment("⏭️ Skipped {$user->name}");

                continue;
            }

            $this->assignRoleToUser($user, $selectedOption, $gymId);
        }
    }

    /**
     * Update existing user roles.
     */
    private function updateExistingUserRoles(): void
    {
        $this->command->info('🔄 Updating existing user roles...');

        $gymId = null;
        if ($this->command->confirm('Do you want to work with a specific gym?', false)) {
            $gyms = $this->getAvailableGyms();
            $selectedGym = $this->command->choice('Select gym:', array_column($gyms, 'name'), 0);
            $gym = collect($gyms)->firstWhere('name', $selectedGym);
            $gymId = $gym['id'];
        }

        $usersWithRoles = User::query()->has('roles')
            ->with(['roles', 'gyms'])
            ->when($gymId, fn ($q) => $q->whereHas('gyms', fn (Builder $gq) => $gq->where('gyms.id', $gymId)))
            ->get();

        if ($usersWithRoles->isEmpty()) {
            $this->command->info('No users with roles found.');

            return;
        }

        $roleChoices = $this->getRoleChoices($gymId);
        $roleChoices[] = 'Remove all roles';

        foreach ($usersWithRoles as $user) {
            $currentRoles = $user->roles->pluck('name')->implode(', ');
            $gymRoles = $user->gyms->map(fn ($g) => "Gym {$g->name}: {$g->pivot->role}")->implode(', ');

            $this->command->info("User: {$user->name} ({$user->email})");
            $this->command->info("  Current roles: {$currentRoles}");
            $this->command->info("  Gym roles: {$gymRoles}");

            if (! $this->command->confirm("Update roles for {$user->name}?", false)) {
                continue;
            }

            $selectedRole = $this->command->choice("Select new role for {$user->name}:", $roleChoices);

            if ($selectedRole === 'Remove all roles') {
                $user->syncRoles([]);
                $user->gyms()->detach();
                $this->command->info("✅ Removed all roles from {$user->name}");
            } else {
                $this->changeUserRole($user, $selectedRole, $gymId);
            }
        }
    }

    /**
     * Bulk assign role to users by email list.
     */
    private function bulkAssignRolesByEmail(): void
    {
        $this->command->info('📧 Bulk assign role to users by email list...');

        $gyms = $this->getAvailableGyms();
        if (blank($gyms)) {
            $this->command->error('No gyms found.');

            return;
        }

        $selectedGym = $this->command->choice('Select gym:', array_column($gyms, 'name'), 0);
        $gym = collect($gyms)->firstWhere('name', $selectedGym);
        $gymId = $gym['id'];

        $this->ensureRolesExist($gymId);

        $roleChoices = $this->getRoleChoices($gymId);
        $selectedRole = $this->command->choice('Select role to assign:', $roleChoices, 0);

        $this->command->info('Enter email addresses (one per line, empty line to finish):');
        $emails = [];

        while (true) {
            $email = $this->command->ask('Email (or press enter to finish)');
            if (blank($email)) {
                break;
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            } else {
                $this->command->warn("Invalid email: {$email}");
            }
        }

        if (blank($emails)) {
            $this->command->info('No emails provided.');

            return;
        }

        $this->command->info("Assigning role '{$selectedRole}' to " . count($emails) . ' users...');

        DB::beginTransaction();
        try {
            foreach ($emails as $email) {
                $user = User::query()->where('email', $email)->first();
                if (! $user) {
                    $this->command->warn("User not found: {$email}");

                    continue;
                }

                $this->assignRoleToUser($user, $selectedRole, $gymId);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Failed to assign roles: ' . $e->getMessage());
        }
    }

    /**
     * Get available role choices for selection.
     *
     * @return array<int, string>
     */
    private function getRoleChoices(?int $gymId): array
    {
        return Role::query()->where('guard_name', 'web')
            ->where(fn (Builder $q) => $q->where('gym_id', $gymId)->orWhereNull('gym_id'))
            ->pluck('name')
            ->toArray();
    }

    /**
     * Assign a role to a user with gym context.
     */
    private function assignRoleToUser(User $user, string $roleName, int $gymId): void
    {
        $role = $this->getRole($roleName, $gymId);

        if (! $role) {
            $this->command->error("Role '{$roleName}' not found.");

            return;
        }

        // Set team context for Spatie permissions
        setPermissionsTeamId($gymId);

        $user->assignRole($role);
        $this->stats['roles_assigned']++;

        // Attach to gym if not super_admin
        $roleEnum = DefaultRole::tryFrom($roleName);
        if ($roleEnum?->isTenantRole()) {
            $user->gyms()->syncWithoutDetaching([
                $gymId => ['role' => $roleName, 'active' => true],
            ]);
            $this->stats['gym_attachments']++;
        }

        $this->command->info("✅ Assigned role '{$roleName}' to {$user->name}");
    }

    /**
     * Change a user's role.
     */
    private function changeUserRole(User $user, string $newRoleName, ?int $gymId): void
    {
        // Sync to new role (removes old roles)
        $user->syncRoles([$newRoleName]);
        $this->stats['roles_assigned']++;

        $roleEnum = DefaultRole::tryFrom($newRoleName);

        // Update gym relationships
        if ($roleEnum?->isTenantRole()) {
            if ($gymId) {
                $user->gyms()->syncWithoutDetaching([
                    $gymId => ['role' => $newRoleName, 'active' => true],
                ]);
            } else {
                // Update all gym relationships
                $gymIds = $user->gyms->pluck('id')->toArray();
                $syncData = [];
                foreach ($gymIds as $id) {
                    $syncData[$id] = ['role' => $newRoleName, 'active' => true];
                }
                $user->gyms()->sync($syncData);
            }
        }

        $this->command->info("✅ Updated role to '{$newRoleName}' for {$user->name}");
    }

    /**
     * Create or update user.
     */
    private function createOrUpdateUser(array $userData): User
    {
        $user = User::query()->firstOrCreate(['email' => $userData['email']], [
            'name' => $userData['name'],
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $isNew = $user->wasRecentlyCreated;
        $isNew ? $this->stats['users_created']++ : $this->stats['users_updated']++;

        $gymId = $userData['gym_id'];
        $roleName = $userData['role'];

        // Assign role with gym context
        $this->assignRoleToUser($user, $roleName, $gymId);

        $action = $isNew ? 'Created' : 'Updated';
        $this->command->info("✅ {$action} user: {$user->email} with role: {$roleName}");

        return $user;
    }

    /**
     * Create profile for user based on role.
     */
    private function createProfileForUser(User $user, string $role, ?array $profileData, int $gymId): void
    {
        if (! $profileData) {
            return;
        }

        match ($role) {
            DefaultRole::TRAINER->value => $this->createTrainerProfile($user, $profileData, $gymId),
            DefaultRole::GYM_MEMBER->value => $this->createMemberProfile($user, $profileData, $gymId),
            default => null,
        };
    }

    /**
     * Create trainer profile.
     */
    private function createTrainerProfile(User $user, array $profileData, int $gymId): void
    {
        if (class_exists(Trainer::class)) {
            Trainer::query()->firstOrCreate(['user_id' => $user->id, 'gym_id' => $gymId], array_merge([
                'user_id' => $user->id,
                'gym_id' => $gymId,
                'name' => $user->name,
                'email' => $user->email,
                'status' => 'active',
            ], $profileData));
        }
    }

    /**
     * Create member profile.
     */
    private function createMemberProfile(User $user, array $profileData, int $gymId): void
    {
        if (class_exists(GymMember::class)) {
            GymMember::query()->firstOrCreate(['user_id' => $user->id, 'gym_id' => $gymId], array_merge([
                'user_id' => $user->id,
                'gym_id' => $gymId,
                'name' => $user->name,
                'email' => $user->email,
                'status' => 'active',
            ], $profileData));
        }
    }

    /**
     * Display final summary.
     */
    private function displayFinalSummary(): void
    {
        $this->command->newLine();
        $this->command->info(str_repeat('=', 80));
        $this->command->info('📊 SEEDING SUMMARY');
        $this->command->info(str_repeat('=', 80));

        $this->command->table(
            ['Metric', 'Count'],
            [
                ['Users Created', $this->stats['users_created']],
                ['Users Updated', $this->stats['users_updated']],
                ['Roles Created', $this->stats['roles_created']],
                ['Roles Assigned', $this->stats['roles_assigned']],
                ['Gym Attachments', $this->stats['gym_attachments']],
            ],
        );

        // Display gym-wise summary
        $this->displayGymWiseSummary();
    }

    /**
     * Display gym-wise user summary.
     */
    private function displayGymWiseSummary(): void
    {
        $gyms = $this->getAvailableGyms();

        // Global users summary
        $globalUsers = User::query()->has('roles')
            ->doesntHave('gyms')
            ->with('roles')
            ->get();

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
            $this->command->newLine();
        }

        // Gym-wise summary
        foreach ($gyms as $gym) {
            $this->command->info("🏢 GYM: {$gym['name']} (ID: {$gym['id']})");

            $gymUsers = DB::table('gym_user')
                ->where('gym_id', $gym['id'])
                ->where('active', true)
                ->select('role', DB::raw('count(*) as count'))
                ->groupBy('role')
                ->get();

            if ($gymUsers->isNotEmpty()) {
                foreach ($gymUsers as $row) {
                    $this->command->info("  🔹 {$row->role}: {$row->count} users");
                }
            } else {
                $this->command->info('No users assigned');
            }
            $this->command->newLine();
        }

        // Overall totals
        $totalUsers = User::query()->count();
        $usersWithRoles = User::query()->has('roles')->count();
        $usersWithoutRoles = $totalUsers - $usersWithRoles;

        $this->command->info(str_repeat('-', 80));
        $this->command->info("📈 Total users: {$totalUsers}");
        $this->command->info("✅ Users with roles: {$usersWithRoles}");
        $this->command->info("❌ Users without roles: {$usersWithoutRoles}");

        if ($usersWithoutRoles > 0) {
            $this->command->warn("⚠️  {$usersWithoutRoles} users don't have roles assigned!");
        }

        $this->command->info(str_repeat('=', 80));
    }
}
