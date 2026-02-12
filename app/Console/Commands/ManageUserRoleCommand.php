<?php

namespace App\Console\Commands;

use App\Enums\DefaultRole;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class ManageUserRoleCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'user:role
                            {--user= : User ID or email to modify}
                            {--role= : Role to assign (see DefaultRole enum)}
                            {--gym= : Gym ID for gym-specific role assignment}
                            {--action= : Action to perform: assign, change, remove, list}
                            {--create-super-admin : Create a new super-admin user}
                            {--list-users : List all users with their roles}
                            {--list-roles : List all available roles}';

    /**
     * The console command description.
     */
    protected $description = 'Manage user roles - assign, change, or remove roles from users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Reset permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Handle flags first
        if ($this->option('create-super-admin')) {
            return $this->createSuperAdmin();
        }

        if ($this->option('list-users')) {
            return $this->listUsersWithRoles();
        }

        if ($this->option('list-roles')) {
            return $this->listAvailableRoles();
        }

        // Interactive mode if no flags
        if (! $this->option('user') && ! $this->option('action')) {
            return $this->interactiveMode();
        }

        // Command-line mode with options
        return $this->commandLineMode();
    }

    /**
     * Interactive mode using Laravel Prompts.
     */
    private function interactiveMode(): int
    {
        $action = select(
            label: 'What would you like to do?',
            options: [
                'assign' => 'Assign role to a user',
                'change' => 'Change user\'s existing role',
                'remove' => 'Remove role from a user',
                'list' => 'List users with roles',
                'create' => 'Create super-admin user',
                'bulk' => 'Bulk role assignment',
            ],
        );

        return match ($action) {
            'assign' => $this->assignRoleInteractive(),
            'change' => $this->changeRoleInteractive(),
            'remove' => $this->removeRoleInteractive(),
            'list' => $this->listUsersWithRoles(),
            'create' => $this->createSuperAdmin(),
            'bulk' => $this->bulkRoleAssignment(),
            default => Command::FAILURE,
        };
    }

    /**
     * Search and select a user.
     */
    private function selectUser(): ?User
    {
        $userIdentifier = search(
            label: 'Search for a user (by name or email):',
            placeholder: 'Type to search...',
            options: function (string $value) {
                if (strlen($value) < 2) {
                    return [];
                }

                return User::where('name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%")
                    ->limit(10)
                    ->get()
                    ->mapWithKeys(fn (User $user) => [
                        $user->id => "{$user->name} ({$user->email})",
                    ])
                    ->all();
            },
        );

        return User::find($userIdentifier);
    }

    /**
     * Select a gym.
     */
    private function selectGym(): ?Gym
    {
        $gyms = Gym::all();
        if ($gyms->isEmpty()) {
            $this->error('No gyms found. Please create a gym first.');

            return null;
        }

        $gymId = select(
            label: 'Select gym:',
            options: $gyms->mapWithKeys(fn (Gym $gym) => [
                $gym->id => $gym->name,
            ])->all(),
        );

        return Gym::find($gymId);
    }

    /**
     * Select a role with gym context.
     */
    private function selectRole(?int $gymId = null): ?string
    {
        $roles = [];

        foreach (DefaultRole::cases() as $role) {
            // For tenant roles, check if role exists for gym
            if ($role->isTenantRole() && $gymId) {
                $roles[$role->value] = $role->getLabel() . ' (Gym-scoped)';
            } elseif (! $role->isTenantRole()) {
                $roles[$role->value] = $role->getLabel() . ' (Global)';
            }
        }

        return select(
            label: 'Select role:',
            options: $roles,
        );
    }

    /**
     * Assign role interactively.
     */
    private function assignRoleInteractive(): int
    {
        $user = $this->selectUser();
        if (! $user) {
            $this->error('User not found.');

            return Command::FAILURE;
        }

        $this->displayUserInfo($user);

        $roleEnum = DefaultRole::tryFrom($this->selectRole());

        if (! $roleEnum) {
            $this->error('Invalid role selected.');

            return Command::FAILURE;
        }

        $gymId = null;
        if ($roleEnum->isTenantRole()) {
            $gym = $this->selectGym();
            if (! $gym) {
                return Command::FAILURE;
            }
            $gymId = $gym->id;
            $this->ensureRoleExists($roleEnum->value, $gymId);
        }

        return $this->assignRole($user, $roleEnum->value, $gymId);
    }

    /**
     * Change role interactively.
     */
    private function changeRoleInteractive(): int
    {
        $user = $this->selectUser();
        if (! $user) {
            $this->error('User not found.');

            return Command::FAILURE;
        }

        $this->displayUserInfo($user);

        // Check for roles including gym-scoped ones by querying directly
        // This is necessary because $user->roles only returns roles for the current team context
        $existingRoles = $this->getExistingRoles($user);

        if ($existingRoles->isEmpty()) {
            $this->warn('This user has no existing roles. Use "assign" instead.');

            return Command::FAILURE;
        }

        $roleDisplay = $existingRoles->map(function ($role) {
            return $role->gym_id ? "{$role->name} (gym:{$role->gym_id})" : $role->name;
        })->implode(', ');

        $this->info('Current roles: ' . $roleDisplay);

        $roleEnum = DefaultRole::tryFrom($this->selectRole());

        if (! $roleEnum) {
            $this->error('Invalid role selected.');

            return Command::FAILURE;
        }

        $gymId = null;
        if ($roleEnum->isTenantRole()) {
            $gym = $this->selectGym();
            if (! $gym) {
                return Command::FAILURE;
            }
            $gymId = $gym->id;
            $this->ensureRoleExists($roleEnum->value, $gymId);
        }

        return $this->changeRole($user, $roleEnum->value, $gymId);
    }

    /**
     * Remove role interactively.
     */
    private function removeRoleInteractive(): int
    {
        $user = $this->selectUser();
        if (! $user) {
            $this->error('User not found.');

            return Command::FAILURE;
        }

        $this->displayUserInfo($user);

        if ($user->roles->isEmpty()) {
            $this->warn('This user has no roles to remove.');

            return Command::FAILURE;
        }

        $rolesToRemove = multiselect(
            label: 'Select roles to remove:',
            options: $user->roles->mapWithKeys(fn ($role) => [
                $role->name => $role->name,
            ])->all(),
        );

        if (empty($rolesToRemove)) {
            $this->info('No roles selected for removal.');

            return Command::SUCCESS;
        }

        $removeFromGyms = confirm(
            label: 'Also remove user from associated gyms?',
            default: false,
        );

        return $this->removeRoles($user, $rolesToRemove, $removeFromGyms);
    }

    /**
     * Bulk role assignment.
     */
    private function bulkRoleAssignment(): int
    {
        $gym = $this->selectGym();
        if (! $gym) {
            return Command::FAILURE;
        }

        $roleEnum = DefaultRole::tryFrom($this->selectRole($gym->id));

        if (! $roleEnum) {
            $this->error('Invalid role selected.');

            return Command::FAILURE;
        }

        $this->ensureRoleExists($roleEnum->value, $gym->id);

        $this->info('Enter email addresses (one per line, empty line to finish):');
        $emails = [];

        while (true) {
            $email = text(label: 'Email (or press enter to finish):', default: '');
            if (empty($email)) {
                break;
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            } else {
                $this->warn("Invalid email: {$email}");
            }
        }

        if (empty($emails)) {
            $this->info('No emails provided.');

            return Command::SUCCESS;
        }

        $this->info("Assigning role '{$roleEnum->value}' to " . count($emails) . ' users...');

        $successCount = 0;
        $failCount = 0;

        DB::beginTransaction();
        try {
            foreach ($emails as $email) {
                $user = User::where('email', $email)->first();
                if (! $user) {
                    $this->warn("User not found: {$email}");
                    $failCount++;

                    continue;
                }

                $this->assignRole($user, $roleEnum->value, $gym->id);
                $successCount++;
            }
            DB::commit();

            $this->info("✅ Assigned roles to {$successCount} users. Failed: {$failCount}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Command-line mode with options.
     */
    private function commandLineMode(): int
    {
        $action = $this->option('action') ?? 'assign';
        $userOption = $this->option('user');
        $roleOption = $this->option('role');
        $gymOption = $this->option('gym');

        // Find user
        $user = is_numeric($userOption)
            ? User::find($userOption)
            : User::where('email', $userOption)->first();

        if (! $user) {
            $this->error("User not found: {$userOption}");

            return Command::FAILURE;
        }

        $gymId = $gymOption ? (int) $gymOption : null;

        return match ($action) {
            'assign' => $this->assignRole($user, $roleOption, $gymId),
            'change' => $this->changeRole($user, $roleOption, $gymId),
            'remove' => $this->removeRoles($user, [$roleOption], true),
            'list' => $this->displayUserInfo($user),
            default => $this->error("Invalid action: {$action}") ?? Command::FAILURE,
        };
    }

    /**
     * Assign role to user.
     */
    private function assignRole(User $user, string $roleName, ?int $gymId): int
    {
        try {
            $role = $this->getRole($roleName, $gymId);
            if (! $role) {
                $this->error("Role '{$roleName}' not found.");

                return Command::FAILURE;
            }

            // Set team context
            if ($gymId) {
                setPermissionsTeamId($gymId);
            }

            // Check if already has role
            if ($user->hasRole($role)) {
                $this->warn("User already has role '{$roleName}'.");

                return Command::SUCCESS;
            }

            $user->assignRole($role);

            // Attach to gym for tenant roles
            $roleEnum = DefaultRole::tryFrom($roleName);
            if ($roleEnum?->isTenantRole() && $gymId) {
                $user->gyms()->syncWithoutDetaching([
                    $gymId => ['role' => $roleName, 'active' => true],
                ]);
            }

            $this->info("✅ Assigned role '{$roleName}' to {$user->name}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to assign role: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Change user's role (removes old roles first).
     */
    private function changeRole(User $user, string $newRoleName, ?int $gymId): int
    {
        try {
            $role = $this->getRole($newRoleName, $gymId);
            if (! $role) {
                $this->error("Role '{$newRoleName}' not found.");

                return Command::FAILURE;
            }

            // Store old roles for reference
            $oldRoles = $this->getExistingRoles($user)->pluck('name')->implode(', ');

            // Set team context
            if ($gymId) {
                setPermissionsTeamId($gymId);
            }

            // Sync to new role only
            $user->syncRoles([$role]);

            // Update gym relationship
            $roleEnum = DefaultRole::tryFrom($newRoleName);
            if ($roleEnum?->isTenantRole() && $gymId) {
                $user->gyms()->syncWithoutDetaching([
                    $gymId => ['role' => $newRoleName, 'active' => true],
                ]);
            }

            $this->info("✅ Changed role from '{$oldRoles}' to '{$newRoleName}' for {$user->name}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to change role: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Remove roles from user.
     */
    private function removeRoles(User $user, array $roleNames, bool $removeFromGyms): int
    {
        try {
            foreach ($roleNames as $roleName) {
                $user->removeRole($roleName);
                $this->info("✅ Removed role '{$roleName}' from {$user->name}");
            }

            if ($removeFromGyms) {
                $user->gyms()->detach();
                $this->info('✅ Removed user from all gyms');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to remove roles: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Create super-admin user.
     */
    private function createSuperAdmin(): int
    {
        $this->info('🔐 Creating Super-Admin User...');

        $name = text(label: 'Enter name:', required: true);
        $email = text(
            label: 'Enter email:',
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Invalid email format'
        );

        if (User::where('email', $email)->exists()) {
            $this->error("User with email '{$email}' already exists.");

            return Command::FAILURE;
        }

        $pwd = password(
            label: 'Enter password (minimum 8 characters):',
            validate: fn (string $value) => strlen($value) >= 8 ? null : 'Password must be at least 8 characters'
        );

        $confirmPwd = password(label: 'Confirm password:');

        if ($pwd !== $confirmPwd) {
            $this->error('Passwords do not match.');

            return Command::FAILURE;
        }

        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($pwd),
                'email_verified_at' => now(),
            ]);

            $this->ensureRoleExists(DefaultRole::SUPER_ADMIN->value);
            $role = $this->getRole(DefaultRole::SUPER_ADMIN->value);

            if ($role) {
                $user->assignRole($role);
            }

            // Attach to all gyms if confirmed
            if (confirm(label: 'Attach super-admin to all gyms?', default: false)) {
                $gyms = Gym::all();
                foreach ($gyms as $gym) {
                    $user->gyms()->attach($gym->id, [
                        'role' => DefaultRole::SUPER_ADMIN->value,
                        'active' => true,
                    ]);
                }
            }

            DB::commit();

            table(
                headers: ['Field', 'Value'],
                rows: [
                    ['Name', $name],
                    ['Email', $email],
                    ['Role', DefaultRole::SUPER_ADMIN->value],
                    ['Status', 'Active & Verified'],
                    ['Created At', $user->created_at->format('Y-m-d H:i:s')],
                ],
            );

            $this->info("✅ Super-admin user '{$name}' created successfully!");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed to create super-admin: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * List users with their roles.
     */
    private function listUsersWithRoles(): int
    {
        $users = User::with(['roles', 'gyms'])->get();

        if ($users->isEmpty()) {
            $this->info('No users found.');

            return Command::SUCCESS;
        }

        $rows = $users->map(fn (User $user) => [
            $user->id,
            $user->name,
            $user->email,
            $user->roles->pluck('name')->implode(', ') ?: '-',
            $user->gyms->pluck('name')->implode(', ') ?: '-',
        ])->all();

        table(
            headers: ['ID', 'Name', 'Email', 'Roles', 'Gyms'],
            rows: $rows,
        );

        return Command::SUCCESS;
    }

    /**
     * List available roles.
     */
    private function listAvailableRoles(): int
    {
        $this->info('📋 Available Roles (from DefaultRole enum):');
        $this->newLine();

        $rows = [];
        foreach (DefaultRole::cases() as $role) {
            $rows[] = [
                $role->value,
                $role->getLabel(),
                $role->isTenantRole() ? 'Gym-scoped' : 'Global',
            ];
        }

        table(
            headers: ['Value', 'Label', 'Scope'],
            rows: $rows,
        );

        $this->newLine();
        $this->info('📋 Roles in Database:');

        $dbRoles = Role::with('permissions')
            ->get()
            ->map(fn (Role $role) => [
                $role->id,
                $role->name,
                $role->gym_id ?? 'Global',
                $role->permissions->count(),
            ])
            ->all();

        table(
            headers: ['ID', 'Name', 'Gym ID', 'Permissions'],
            rows: $dbRoles,
        );

        return Command::SUCCESS;
    }

    /**
     * Display user information.
     */
    private function displayUserInfo(User $user): int
    {
        $user->load(['roles', 'gyms']);

        $roles = $this->getExistingRoles($user);

        table(
            headers: ['Field', 'Value'],
            rows: [
                ['ID', $user->id],
                ['Name', $user->name],
                ['Email', $user->email],
                ['Roles', $roles->pluck('name')->implode(', ') ?: 'None'],
                ['Gyms', $user->gyms->pluck('name')->implode(', ') ?: 'None'],
                ['Status', $user->status->getLabel() ?? 'N/A'],
                ['Email Verified', $user->email_verified_at ? 'Yes' : 'No'],
            ],
        );

        return Command::SUCCESS;
    }

    /**
     * Ensure role exists in database.
     */
    private function ensureRoleExists(string $roleName, ?int $gymId = null): void
    {
        $roleEnum = DefaultRole::tryFrom($roleName);

        $query = Role::where('name', $roleName)->where('guard_name', 'web');

        if ($roleEnum?->isTenantRole() && $gymId) {
            $query->where('gym_id', $gymId);
        } else {
            $query->whereNull('gym_id');
        }

        if (! $query->exists()) {
            Role::create([
                'name' => $roleName,
                'guard_name' => 'web',
                'gym_id' => ($roleEnum?->isTenantRole() && $gymId) ? $gymId : null,
            ]);
            $this->info("Created role '{$roleName}'" . ($gymId ? " for gym {$gymId}" : ''));
        }
    }

    /**
     * Get role from database.
     */
    private function getRole(string $roleName, ?int $gymId = null): ?Role
    {
        $roleEnum = DefaultRole::tryFrom($roleName);

        $query = Role::where('name', $roleName)->where('guard_name', 'web');

        if ($roleEnum?->isTenantRole() && $gymId) {
            $query->where('gym_id', $gymId);
        } else {
            $query->whereNull('gym_id');
        }

        return $query->first();
    }

    private function getExistingRoles(User $user)
    {
        return DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', User::class)
            ->select('roles.name', 'roles.gym_id')
            ->get();
    }
}
