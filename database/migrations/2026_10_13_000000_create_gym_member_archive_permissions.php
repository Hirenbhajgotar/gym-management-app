<?php

declare(strict_types=1);

use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\GymMemberResource;
use App\Models\Permission;
use App\Permissions\TeamPermissionRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Create and grant the `archive_gym_member` / `unarchive_gym_member` permissions.
 *
 * `GymMemberResource` ships the Member Archive feature behind the `archive` and
 * `unarchive` abilities, so those two permissions gate code that is already in
 * production. Creating them here means an UPGRADE grants them the same way any
 * other schema change is applied, instead of depending on an operator
 * remembering to run `php artisan permissions:sync` after the deploy. That
 * command stays the mechanism for permissions discovered in the future, where
 * nobody has shipped a feature against the permission yet.
 *
 * On a FRESH install there are no roles yet when this runs — migrations precede
 * seeders — so the grant step finds nothing to attach to and the two permissions
 * exist with zero grants. That is deliberate and fail-closed rather than
 * broken: nothing can archive a member until an operator runs
 * `permissions:sync`, which creates the roles and applies this resource's
 * `defaultRolePermissions()`. The docblock used to claim the dependency on that
 * manual command was removed entirely, which was only true for upgrades.
 *
 * `Database\Seeders\PermissionDescriptionSeeder` covers the shared action list
 * but the display metadata is set here as well, so the permission matrix renders
 * labelled rows even on an install that never runs that seeder.
 */
return new class() extends Migration
{
    private const GUARD = 'web';

    private const CATEGORY = 'gym_member';

    /**
     * Permission name => display name.
     *
     * @var array<string, string>
     */
    private const PERMISSIONS = [
        'archive_gym_member' => 'Archive Member',
        'unarchive_gym_member' => 'Unarchive Member',
    ];

    /**
     * The Filament class whose defaultRolePermissions() decides which
     * gym-scoped roles are granted these two abilities. Read at run time by
     * {@see self::targetRoleIds()} so the decision has exactly one home.
     *
     * @var class-string<\App\Filament\Admin\Resources\GymMemberResource>
     */
    private const RESOURCE = GymMemberResource::class;

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name => $displayName) {
            $this->ensurePermission($name, $displayName);
        }

        // pluck() hands back the model's raw primary keys; cast at this boundary
        // so the rest of the migration works with one honest type.
        /** @var array<int, int> $permissionIds */
        $permissionIds = Permission::query()
            ->where('guard_name', self::GUARD)
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $this->grant($permissionIds);

        $this->flushPermissionCache();
    }

    /**
     * Precise inverse: drops these two permissions and only their grants,
     * leaving every other permission and role untouched.
     *
     * The grants need no explicit delete: `role_has_permissions` keys on
     * (permission_id, role_id) with an ON DELETE CASCADE foreign key to
     * `permissions`, so removing the permission rows removes exactly their
     * pivots and nothing else.
     */
    public function down(): void
    {
        $names = array_keys(self::PERMISSIONS);

        Permission::query()
            ->where('guard_name', self::GUARD)
            ->whereIn('name', $names)
            ->delete();

        $this->flushPermissionCache();
    }

    private function ensurePermission(string $name, string $displayName): Permission
    {
        $attributes = [
            'display_name' => $displayName,
            'description' => 'Can ' . $displayName,
            'category' => self::CATEGORY,
        ];

        $permission = Permission::query()->firstOrCreate(
            ['name' => $name, 'guard_name' => self::GUARD],
            $attributes,
        );

        // firstOrCreate leaves a pre-existing row untouched, and
        // `permissions:sync` bulk-inserts permissions with no metadata at all.
        // Each column is checked on its own so this never overwrites a better
        // value an operator wrote by hand for one field while leaving the rest
        // blank — checking only display_name would rewrite description and
        // category out from under them.
        $needsBackfill = false;

        foreach ($attributes as $column => $value) {
            if (blank($permission->{$column})) {
                $permission->forceFill([$column => $value]);
                $needsBackfill = true;
            }
        }

        if ($needsBackfill) {
            $permission->save();
        }

        return $permission;
    }

    /**
     * @param  array<int, int>  $permissionIds
     */
    private function grant(array $permissionIds): void
    {
        $rows = [];

        foreach ($this->targetRoleIds() as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $rows[] = [
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ];
            }
        }

        if ($rows === []) {
            return;
        }

        // insertOrIgnore and never syncPermissions()/permissions()->detach():
        // the pivot's primary key is (permission_id, role_id) so a repeat
        // insert is already a no-op, and this migration must stay strictly
        // additive. syncPermissions() REPLACES a role's entire permission set,
        // which would silently strip every hand-configured permission a gym's
        // owner or manager role holds.
        DB::table('role_has_permissions')->insertOrIgnore($rows);
    }

    /**
     * Role ids that must hold the archived-member permissions.
     *
     * The gym-scoped role names are READ from
     * `GymMemberResource::defaultRolePermissions()` rather than copied here.
     * A hand-copied list was a third place to remember when a role's access
     * changed; the migration would keep granting (or failing to grant) against
     * a stale copy of a decision that already lived somewhere declarative. The
     * Resource is instantiated without its constructor and without a booted
     * Filament panel, which is the same thing RolesAndPermissionsService and the
     * migration's own test do.
     *
     * Roles are queried with an explicit `gym_id` constraint on purpose.
     * `setPermissionsTeamId()` mutates process-global state on the registrar,
     * which leaks team context into anything else running in the same process
     * (Octane, queue workers) — so a migration must not use it. Team scoping
     * comes from `roles.gym_id` and `model_has_roles.gym_id`; the
     * `role_has_permissions` pivot itself carries no gym column.
     *
     * @return array<int, int>
     */
    private function targetRoleIds(): array
    {
        $declared = $this->resource()->defaultRolePermissions();

        $names = [];

        foreach ($declared as $roleName => $rolePermissions) {
            if (
                in_array('archive', $rolePermissions, true)
                && in_array('unarchive', $rolePermissions, true)
            ) {
                $names[] = $roleName;
            }
        }

        // gym_owner is never listed in defaultRolePermissions() because it
        // holds every permission by definition — permissions:sync grants it
        // Permission::all() rather than a declared subset. Reading only the
        // declaration would therefore have dropped it here, which is how this
        // migration ended up granting the feature to manager and nobody else.
        $names[] = DefaultRole::GYM_OWNER->value;

        /** @var array<int, string> $names */
        $names = array_values(array_unique($names));

        /** @var array<int, int> $roleIds */
        $roleIds = Role::query()
            ->where('guard_name', self::GUARD)
            ->where(function ($query) use ($names): void {
                // Global super_admin role, which exists without a gym.
                $query->where(
                    fn ($roles) => $roles
                        ->where('name', DefaultRole::SUPER_ADMIN->value)
                        ->whereNull('gym_id')
                );

                $query->orWhere(
                    fn ($roles) => $roles
                        ->whereIn('name', $names)
                        ->whereNotNull('gym_id')
                );
            })
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return $roleIds;
    }

    /**
     * The Filament class whose defaultRolePermissions() decides which
     * gym-scoped roles are granted these two abilities.
     *
     * Instantiated without its constructor and without a booted Filament
     * panel, which is the same thing RolesAndPermissionsService and the
     * migration's own test do: the declarations are plain arrays and need no
     * runtime. No instanceof guard — the constant is typed to the class, so
     * swapping it for a class without the method is a static failure rather
     * than a silent "granted to nobody".
     */
    private function resource(): GymMemberResource
    {
        return (new ReflectionClass(self::RESOURCE))->newInstanceWithoutConstructor();
    }

    private function flushPermissionCache(): void
    {
        try {
            // No instanceof guard: AppServiceProvider binds PermissionRegistrar
            // to TeamPermissionRegistrar unconditionally, so the check could
            // only ever be true. Calling the per-gym flusher directly means a
            // swapped binding is a static failure rather than a silent return
            // to leaving every tenant on its stale permission set.
            /** @var TeamPermissionRegistrar $registrar Bound by AppServiceProvider. */
            $registrar = resolve(PermissionRegistrar::class);
            $registrar->forgetAllCachedPermissions();
        } catch (Throwable $exception) {
            // A cache flush failure must never abort a migration that has
            // already committed its rows; the cache is rebuilt on next write.
            report($exception);
        }
    }
};
