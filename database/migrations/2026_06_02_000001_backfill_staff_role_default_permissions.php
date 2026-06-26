<?php

use App\Models\Gym;
use App\Models\PermissionSetting;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

/**
 * Backfill manager + receptionist roles per gym with the same default
 * permission set that `ManagerPermissionsPage::getQuickSetupPermissions()`
 * (and `ReceptionistPermissionsPage::getQuickSetupPermissions()`) grants.
 *
 * Source of truth — copied verbatim from the Filament Livewire components
 * to avoid importing them in a migration (they depend on Filament runtime).
 *
 * Idempotent: `Role::firstOrCreate` + `syncPermissions` overwrites the
 * current permission set. Running twice produces the same final state.
 *
 * Honors `permission_settings` overrides: if a row exists for the role,
 * its `default_permissions` JSON is used instead of the hardcoded list.
 */
return new class extends Migration
{
    /**
     * Default permission set for both `manager` and `receptionist`.
     *
     * @var array<int, string>
     */
    private const QUICK_SETUP_PERMISSIONS = [
        'view_gym_member',
        'view_any_gym_member',
        'create_gym_member',
        'update_gym_member',
        'view_membership_plan',
        'view_any_membership_plan',
        'create_membership_plan',
        'update_membership_plan',
        'view_payment',
        'view_any_payment',
        'create_payment',
        'update_payment',
        'widget_stats_overview',
        'widget_recent_members',
        'widget_membership_expiry_alerts_chart',
        'widget_todays_birthdays',
        'access_notifications',
        'manage_member_notes',
        'send_notifications',
        'access_member_communication',
        'view_lead',
        'view_any_lead',
        'create_lead',
        'update_lead',
    ];

    public function up(): void
    {
        // $permissionRegistrar = app(PermissionRegistrar::class);

        // foreach (Gym::all() as $gym) {
        //     DB::transaction(function () use ($gym, $permissionRegistrar) {
        //         // Set team context BEFORE creating/looking up roles so cache
        //         // keys are gym-scoped.
        //         setPermissionsTeamId($gym->id);
        //         $permissionRegistrar->forgetCachedPermissions();

        //         foreach (self::STAFF_ROLES as $roleName) {
        //             $role = Role::firstOrCreate(
        //                 [
        //                     'name' => $roleName,
        //                     'guard_name' => 'web',
        //                     'gym_id' => $gym->id,
        //                 ],
        //             );

        //             $permissions = $this->resolvePermissionsFor($roleName);

        //             // Detach existing permissions first so this migration
        //             // is idempotent and the role ends with exactly the
        //             // resolved set (mirrors `syncPermissions` semantics).
        //             $role->permissions()->detach();

        //             foreach ($permissions as $permissionName) {
        //                 $permission = Permission::firstOrCreate(
        //                     [
        //                         'name' => $permissionName,
        //                         'guard_name' => 'web',
        //                     ],
        //                 );

        //                 $role->givePermissionTo($permission);
        //             }

        //             // Propagate to existing users who already hold this role
        //             // for this gym so they can use the new permissions
        //             // immediately after deploy.
        //             $this->syncRoleToExistingUsers($role, $gym);
        //         }
        //     });
        // }

        // // Final cache flush so a subsequent `php artisan` invocation sees
        // // the new permission bindings.
        // $permissionRegistrar->forgetCachedPermissions();
    }

    /**
     * Reverse the migration. Safe no-op: we do not delete permissions
     * (they may have been edited by admins since backfill). Logged for
     * auditability.
     */
    public function down(): void
    {
        Log::warning('backfill_staff_role_default_permissions: down() is a no-op. Manager and receptionist roles retain their synced permissions (admin edits preserved).');
    }

    /**
     * Resolve the permission list to use for the given role.
     *
     * Priority:
     *   1. `permission_settings` override (admin-configured JSON)
     *   2. Hardcoded `QUICK_SETUP_PERMISSIONS` (mirrors `ManagerPermissionsPage`)
     *
     * @return array<int, string>
     */
    private function resolvePermissionsFor(string $roleName): array
    {
        $setting = PermissionSetting::query()
            ->where('role', $roleName)
            ->first();

        if ($setting !== null && filled($setting->default_permissions)) {
            return array_values($setting->default_permissions);
        }

        return self::QUICK_SETUP_PERMISSIONS;
    }

    /**
     * Sync the role's permission set to every user that already has the
     * role in the current gym context.
     */
    private function syncRoleToExistingUsers(Role $role, Gym $gym): void
    {
        $roleName = $role->name;

        $users = User::whereHas('roles', function (Builder $query) use ($roleName, $gym) {
            $query
                ->where('name', $roleName)
                ->where('roles.gym_id', $gym->id);
        })->get();

        $permissionNames = $role->permissions->pluck('name')->all();

        foreach ($users as $user) {
            $user->permissions()->detach();
            foreach ($permissionNames as $permissionName) {
                $user->givePermissionTo($permissionName);
            }
        }
    }
};
