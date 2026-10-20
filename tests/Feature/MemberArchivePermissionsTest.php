<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DefaultRole;
use App\Enums\PermissionCategory;
use App\Filament\Admin\Resources\GymMemberResource;
use App\Models\Gym;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberArchivePermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    private const ARCHIVE_PERMISSIONS = ['archive_gym_member', 'unarchive_gym_member'];

    private const CONTROL_PERMISSION = 'view_any_gym_member';

    /**
     * `require` re-evaluates the file, so the resolved instance is cached to
     * declare the anonymous class once per process — the same thing
     * Illuminate\Database\Migrations\Migrator does internally.
     */
    private static ?object $migration = null;

    protected Gym $gym;

    protected Role $superAdminRole;

    protected Role $gymOwnerRole;

    protected Role $managerRole;

    /**
     * The control permission, held as a model instance.
     *
     * `givePermissionTo()` must be handed this rather than the name: Spatie
     * resolves a string through `findByName()`, and this app's
     * TeamPermissionRegistrar loads permissions by joining the roles of the
     * CURRENT gym. A permission that is not yet attached to any role in that gym
     * is therefore invisible to that lookup and the grant throws — which is
     * exactly the chicken-and-egg these two tests are trying to set up.
     */
    private Permission $controlPermission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        // The migration grants permissions to roles; it does not create them.
        $this->superAdminRole = $this->createRole(DefaultRole::SUPER_ADMIN->value, null);
        $this->gymOwnerRole = $this->createRole(DefaultRole::GYM_OWNER->value, $this->gym->id);
        $this->managerRole = $this->createRole(DefaultRole::MANAGER->value, $this->gym->id);

        $this->controlPermission = Permission::query()->firstOrCreate([
            'name' => self::CONTROL_PERMISSION,
            'guard_name' => 'web',
        ]);
    }

    public function test_up_creates_both_permissions_with_display_metadata(): void
    {
        $this->migration()->up();

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', self::ARCHIVE_PERMISSIONS)
            ->get()
            ->keyBy('name');

        $this->assertCount(2, $permissions, 'Both archive permissions should exist.');

        $this->assertSame('Archive Member', $permissions['archive_gym_member']->display_name);
        $this->assertSame('Can Archive Member', $permissions['archive_gym_member']->description);
        $this->assertSame(PermissionCategory::GYM_MEMBER, $permissions['archive_gym_member']->category);

        $this->assertSame('Unarchive Member', $permissions['unarchive_gym_member']->display_name);
        $this->assertSame('Can Unarchive Member', $permissions['unarchive_gym_member']->description);
        $this->assertSame(PermissionCategory::GYM_MEMBER, $permissions['unarchive_gym_member']->category);
    }

    public function test_up_grants_both_permissions_to_the_global_super_admin_role(): void
    {
        $this->migration()->up();

        $this->assertNull($this->superAdminRole->gym_id, 'super_admin must be the global role.');
        $this->assertEqualsCanonicalizing(
            self::ARCHIVE_PERMISSIONS,
            $this->grantedPermissionNames($this->superAdminRole->getKey()),
        );
    }

    public function test_up_grants_both_permissions_to_the_gym_owner_role(): void
    {
        $this->migration()->up();

        $this->assertSame($this->gym->id, $this->gymOwnerRole->gym_id);
        $this->assertEqualsCanonicalizing(
            self::ARCHIVE_PERMISSIONS,
            $this->grantedPermissionNames($this->gymOwnerRole->getKey()),
        );
    }

    public function test_up_grants_both_permissions_to_the_role_declared_by_the_gym_member_resource(): void
    {
        // Instantiated the same way RolesAndPermissionsService does, so the
        // test never depends on the Filament panel runtime being booted.
        $declared = (new ReflectionClass(GymMemberResource::class))
            ->newInstanceWithoutConstructor()
            ->defaultRolePermissions();

        $expectedRoles = array_values(array_filter(
            DefaultRole::cases(),
            fn (DefaultRole $role): bool => in_array('archive', $declared[$role->value] ?? [], true)
                && in_array('unarchive', $declared[$role->value] ?? [], true),
        ));

        $this->assertContains(
            DefaultRole::MANAGER,
            $expectedRoles,
            'GymMemberResource::defaultRolePermissions() should still declare archive/unarchive for manager.',
        );

        $this->migration()->up();

        $this->assertEqualsCanonicalizing(
            self::ARCHIVE_PERMISSIONS,
            $this->grantedPermissionNames($this->managerRole->getKey()),
        );
    }

    public function test_up_is_idempotent_when_run_twice(): void
    {
        $this->migration()->up();

        $permissionIds = $this->permissionIds();
        $this->migration()->up();

        $this->assertEqualsCanonicalizing(
            $permissionIds,
            $this->permissionIds(),
            'Re-running up() must not duplicate the permission rows.',
        );

        foreach ([$this->superAdminRole, $this->gymOwnerRole, $this->managerRole] as $role) {
            $this->assertEqualsCanonicalizing(
                self::ARCHIVE_PERMISSIONS,
                $this->grantedPermissionNames($role->getKey()),
                'Re-running up() must not duplicate the role grants.',
            );
        }
    }

    public function test_up_leaves_an_unrelated_permission_untouched(): void
    {
        $this->managerRole->givePermissionTo($this->controlPermission);

        $this->migration()->up();
        $this->migration()->up();

        $granted = $this->grantedPermissionNames($this->managerRole->getKey());

        $this->assertContains(self::CONTROL_PERMISSION, $granted);
        $this->assertDatabaseHas('permissions', ['name' => self::CONTROL_PERMISSION, 'guard_name' => 'web']);
    }

    public function test_down_removes_only_the_archive_grants_and_permissions(): void
    {
        $controlId = $this->controlPermission->getKey();

        $this->managerRole->givePermissionTo($this->controlPermission);

        $this->migration()->up();
        $this->migration()->down();

        $this->assertDatabaseMissing('permissions', ['name' => 'archive_gym_member']);
        $this->assertDatabaseMissing('permissions', ['name' => 'unarchive_gym_member']);

        $this->assertDatabaseHas('permissions', ['id' => $controlId]);
        $this->assertDatabaseHas('role_has_permissions', [
            'permission_id' => $controlId,
            'role_id' => $this->managerRole->getKey(),
        ]);

        // down() is a precise inverse: the two archive permissions and every
        // grant of them go, and nothing else. super_admin and gym_owner were
        // only ever granted those two, so they end up empty; manager keeps the
        // control permission asserted above.
        $this->assertSame([], $this->grantedPermissionNames($this->superAdminRole->getKey()));
        $this->assertSame([], $this->grantedPermissionNames($this->gymOwnerRole->getKey()));
        $this->assertSame(
            [self::CONTROL_PERMISSION],
            $this->grantedPermissionNames($this->managerRole->getKey()),
        );
    }

    public function test_down_is_a_no_op_when_the_permissions_are_already_absent(): void
    {
        $this->migration()->up();
        $this->migration()->down();

        // Rolling back again has nothing left to remove and must not error.
        $this->migration()->down();

        $this->assertDatabaseMissing('permissions', ['name' => 'archive_gym_member']);
        $this->assertDatabaseMissing('permissions', ['name' => 'unarchive_gym_member']);
        $this->assertDatabaseHas('permissions', ['name' => self::CONTROL_PERMISSION]);
    }

    public function test_up_creates_the_permissions_without_grants_when_no_roles_exist_yet(): void
    {
        // A fresh install runs migrations before seeders, so there is nothing to
        // grant to. The permissions must still be created — the feature is then
        // inert and fail-closed until permissions:sync creates the roles — rather
        // than the migration erroring or silently doing nothing.
        Role::query()->delete();

        $this->migration()->up();

        $this->assertDatabaseHas('permissions', ['name' => 'archive_gym_member', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'unarchive_gym_member', 'guard_name' => 'web']);
        $this->assertSame(0, DB::table('role_has_permissions')->count());
    }

    public function test_up_backfills_each_blank_metadata_column_independently(): void
    {
        // A permissions:sync-created row has no metadata at all. An operator who
        // has since written a good description by hand must keep it: the
        // migration backfills blank columns one at a time rather than rewriting
        // all three whenever any one of them is empty.
        //
        // The row already exists — RefreshDatabase runs this migration as part
        // of the schema — so this shapes its metadata rather than inserting it.
        $permission = $this->archivePermission('archive_gym_member');
        $permission->forceFill([
            'display_name' => 'Archive Member',
            'description' => 'Operator-authored description',
            'category' => PermissionCategory::GYM_MEMBER->value,
        ])->save();

        // Blank only the category. A guard that tested display_name and then
        // rewrote all three would replace the operator's description, which is
        // the thing this test exists to prevent.
        $permission->forceFill(['category' => null])->save();

        $this->migration()->up();

        $refreshed = $permission->fresh();

        $this->assertNotNull($refreshed);
        $this->assertSame('Operator-authored description', $refreshed->description);
        $this->assertSame('Archive Member', $refreshed->display_name);
        $this->assertSame(
            PermissionCategory::GYM_MEMBER,
            $refreshed->category,
            'A blank column must be backfilled.'
        );
    }

    public function test_up_fills_a_description_that_is_blank_but_leaves_a_written_one_alone(): void
    {
        $permission = $this->archivePermission('unarchive_gym_member');

        $permission->forceFill([
            'display_name' => 'Unarchive Member',
            'description' => null,
            'category' => null,
        ])->save();

        $this->migration()->up();

        $refreshed = $permission->fresh();

        $this->assertNotNull($refreshed);
        $this->assertSame('Can Unarchive Member', $refreshed->description);
        $this->assertSame(PermissionCategory::GYM_MEMBER, $refreshed->category);
    }

    public function test_archive_actions_are_scoped_to_the_member_resource_and_not_generated_globally(): void
    {
        // archive/unarchive are declared by GymMemberResource through
        // DeclaresPermissionActions. Listing them in the shared config would mint
        // archive_<slug> for every resource on the platform, almost none of which
        // has a policy consuming them.
        $sharedActions = (array) config('filament-permissions.resource_actions');

        $this->assertNotContains('archive', $sharedActions);
        $this->assertNotContains('unarchive', $sharedActions);

        $declared = (new ReflectionClass(GymMemberResource::class))
            ->newInstanceWithoutConstructor();

        $this->assertSame(['archive', 'unarchive'], $declared->getPermissionActions());
    }

    public function test_the_migration_reads_its_roles_from_the_resource_rather_than_a_copied_list(): void
    {
        // Add archive/unarchive to a second role's declaration and the migration
        // must pick it up with no edit here. A hand-copied role list was a third
        // place to remember when a role's access changed.
        $declared = (new ReflectionClass(GymMemberResource::class))
            ->newInstanceWithoutConstructor()
            ->defaultRolePermissions();

        // Roles the Resource declares for BOTH abilities, plus gym_owner — which
        // holds every permission by definition rather than through a declared
        // subset, and is added by the migration explicitly.
        $expectedGymScopedRoleNames = array_values(array_unique([
            ...array_values(array_filter(
                array_keys($declared),
                fn (string $role): bool => in_array('archive', $declared[$role] ?? [], true)
                    && in_array('unarchive', $declared[$role] ?? [], true),
            )),
            DefaultRole::GYM_OWNER->value,
        ]));

        $this->assertContains(DefaultRole::MANAGER->value, $expectedGymScopedRoleNames);
        // receptionist is the role deliberately NOT granted archive, which is
        // what makes the importer gate in MemberArchiveAuthorizationTest
        // meaningful.
        $this->assertNotContains(DefaultRole::RECEPTIONIST->value, $expectedGymScopedRoleNames);

        $this->migration()->up();

        // Invoke the migration (above) and read the grants it actually wrote,
        // rather than only reflecting the Resource: a migration that hardcoded
        // its own role list would pass a Resource-only assertion.
        $grantedGymScopedRoleNames = DB::table('role_has_permissions')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->whereIn('permissions.name', self::ARCHIVE_PERMISSIONS)
            ->whereNotNull('roles.gym_id')
            ->pluck('roles.name')
            ->unique()
            ->values()
            ->all();

        foreach ($expectedGymScopedRoleNames as $roleName) {
            $this->assertContains(
                $roleName,
                $grantedGymScopedRoleNames,
                "Role {$roleName} is declared for archive/unarchive but the migration did not grant it."
            );
        }

        // Two-sided: the roles that are NOT declared for archive/unarchive must
        // not receive it either. receptionist is the deliberate exclusion the
        // importer gate depends on.
        $this->assertNotContains(
            DefaultRole::RECEPTIONIST->value,
            $grantedGymScopedRoleNames,
            'The migration must not grant archive/unarchive to a role the Resource does not declare.'
        );
    }

    public function test_the_migration_permission_names_are_derived_from_the_resource_actions(): void
    {
        // The migration's two names are the third literal copy of
        // "archive"/"unarchive": the Resource declares the actions and the
        // shared config generates the rest. Pin them together so renaming an
        // action on the Resource cannot silently leave the granted permission
        // and the ability the policy checks out of step.
        $declaredActions = (new ReflectionClass(GymMemberResource::class))
            ->newInstanceWithoutConstructor()
            ->getPermissionActions();

        $derivedNames = array_map(fn (string $action): string => "{$action}_gym_member", $declaredActions);

        $this->assertEqualsCanonicalizing(
            $derivedNames,
            self::ARCHIVE_PERMISSIONS,
            'The migration must create exactly the permissions the Resource declares.'
        );

        // Read the MIGRATION's own output rather than only the literal above:
        // re-run it and assert the rows it owns are the derived names. Without
        // this, renaming a Resource action AND the constant together would let a
        // re-hardcoded name list in the migration pass unnoticed.
        $this->migration()->up();

        $this->assertEqualsCanonicalizing(
            $derivedNames,
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', $derivedNames)
                ->pluck('name')
                ->all(),
            'The migration must create exactly the permissions derived from the Resource actions.'
        );
    }

    /**
     * RefreshDatabase runs this migration as part of the schema, so both
     * permissions already exist by the time a test body runs.
     */
    private function archivePermission(string $name): Permission
    {
        $permission = Permission::query()
            ->where('name', $name)
            ->where('guard_name', 'web')
            ->first();

        $this->assertNotNull($permission, "Expected {$name} to exist after the schema ran.");

        return $permission;
    }

    /**
     * @param  int|string|null  $gymId
     */
    private function createRole(string $name, $gymId): Role
    {
        $role = Role::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
            'gym_id' => $gymId,
        ]);

        $this->assertNotNull($role->getKey());

        return $role;
    }

    /**
     * @return array<int, string>
     */
    private function grantedPermissionNames(int|string $roleId): array
    {
        return DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_has_permissions.role_id', $roleId)
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->all();
    }

    /**
     * @return array<int, int|string>
     */
    private function permissionIds(): array
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', self::ARCHIVE_PERMISSIONS)
            ->orderBy('name')
            ->pluck('id')
            ->all();
    }

    private function migration(): object
    {
        return self::$migration ??= require base_path(
            'database/migrations/2026_10_13_000000_create_gym_member_archive_permissions.php'
        );
    }
}
