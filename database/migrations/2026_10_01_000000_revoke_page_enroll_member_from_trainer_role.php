<?php

declare(strict_types=1);

use App\Enums\DefaultRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class() extends Migration
{
    public function up(): void
    {
        $permission = Permission::query()
            ->where('name', 'page_enroll_member')
            ->where('guard_name', 'web')
            ->first();

        if (! $permission) {
            return;
        }

        $roles = Role::query()
            ->whereIn('name', [DefaultRole::TRAINER->value, DefaultRole::RECEPTIONIST->value])
            ->where('guard_name', 'web')
            ->get();

        foreach ($roles as $role) {
            $role->revokePermissionTo($permission);
        }

        $permission->delete();

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new RuntimeException('Rolling back revocation and deletion of page_enroll_member is prohibited because the capability has been retired.');
    }
};
