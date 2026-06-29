<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        if (Schema::hasTable('model_has_roles')) {
            Schema::table('model_has_roles', function (Blueprint $table) use ($pivotRole) {
                // Drop foreign key first
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign([$pivotRole]);
                }

                // Drop the primary key
                $table->dropPrimary();

                // Modify gym_id to be nullable
                $table->unsignedBigInteger('gym_id')->nullable()->change();

                // Create new primary key without gym_id
                $table->primary(
                    [$pivotRole, 'model_id', 'model_type'],
                    'model_has_roles_role_model_type_primary'
                );

                // Recreate foreign key
                if (DB::getDriverName() !== 'sqlite') {
                    $table->foreign($pivotRole)
                        ->references('id')
                        ->on('roles')
                        ->onDelete('cascade');
                }
            });
        }

        if (Schema::hasTable('model_has_permissions')) {
            Schema::table('model_has_permissions', function (Blueprint $table) use ($pivotPermission) {
                // Drop foreign key first
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign([$pivotPermission]);
                }

                // Drop the primary key
                $table->dropPrimary();

                // Modify gym_id to be nullable
                $table->unsignedBigInteger('gym_id')->nullable()->change();

                // Create new primary key without gym_id
                $table->primary(
                    [$pivotPermission, 'model_id', 'model_type'],
                    'model_has_permissions_permission_model_type_primary'
                );

                // Recreate foreign key
                if (DB::getDriverName() !== 'sqlite') {
                    $table->foreign($pivotPermission)
                        ->references('id')
                        ->on('permissions')
                        ->onDelete('cascade');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
