<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keep the ADMS command ledger after its terminal is deleted.
 *
 * `device_id` was `cascadeOnDelete()`, so deleting a device destroyed the only
 * durable evidence of whether a `user_delete` was ever sent — including rows
 * `prunable()` deliberately retains as erasure evidence. The foreign key now
 * nulls on delete and the terminal serial is snapshotted so the ledger still
 * says which hardware the command was addressed to.
 *
 * The `down()` migration is forward-only in practice: restoring the restrictive
 * foreign key would require deleting (or re-binding) ledger rows whose device no
 * longer exists, which destroys audit evidence. It therefore refuses rather than
 * silently discarding those rows.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('adms_device_commands', 'device_serial')) {
            Schema::table('adms_device_commands', function (Blueprint $table) {
                $table->string('device_serial', 255)->nullable()->after('device_id');
            });
        }

        DB::statement(
            'UPDATE adms_device_commands c INNER JOIN biometric_devices d ON d.id = c.device_id
             SET c.device_serial = d.device_serial
             WHERE c.device_serial IS NULL'
        );

        if ($this->hasForeignKeyOn('adms_device_commands', 'device_id')) {
            Schema::table('adms_device_commands', function (Blueprint $table) {
                $table->dropForeign(['device_id']);
            });
        }

        Schema::table('adms_device_commands', function (Blueprint $table) {
            $table->unsignedBigInteger('device_id')->nullable()->change();
        });

        if (! $this->hasForeignKeyOn('adms_device_commands', 'device_id')) {
            Schema::table('adms_device_commands', function (Blueprint $table) {
                $table->foreign('device_id')->references('id')->on('biometric_devices')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::table('adms_device_commands')->whereNull('device_id')->exists()) {
            throw new RuntimeException(
                'Cannot restore the restrictive device_id foreign key: the ledger contains rows whose device was deleted. '
                . 'Re-bind or archive those rows deliberately before rolling back; this migration will not discard erasure evidence.'
            );
        }

        if ($this->hasForeignKeyOn('adms_device_commands', 'device_id')) {
            Schema::table('adms_device_commands', function (Blueprint $table) {
                $table->dropForeign(['device_id']);
            });
        }

        Schema::table('adms_device_commands', function (Blueprint $table) {
            $table->unsignedBigInteger('device_id')->nullable(false)->change();
        });

        if (! $this->hasForeignKeyOn('adms_device_commands', 'device_id')) {
            Schema::table('adms_device_commands', function (Blueprint $table) {
                $table->foreign('device_id')->references('id')->on('biometric_devices')->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('adms_device_commands', 'device_serial')) {
            Schema::table('adms_device_commands', function (Blueprint $table) {
                $table->dropColumn('device_serial');
            });
        }
    }

    /**
     * Whether the table already carries a foreign key on the given column.
     */
    private function hasForeignKeyOn(string $table, string $column): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (in_array($column, $foreignKey['columns'], true)) {
                return true;
            }
        }

        return false;
    }
};
