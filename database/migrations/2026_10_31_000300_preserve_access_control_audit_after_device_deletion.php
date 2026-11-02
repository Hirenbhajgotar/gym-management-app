<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keep the access-control audit trail after its terminal is deleted.
 *
 * `door_events.device_id` and `biometric_event_receipts.device_id` were both
 * `cascadeOnDelete()`, so deleting an in-service terminal destroyed every
 * `unlock_granted`/`unlock_denied`/`unlock_failed` record and webhook receipt
 * for that hardware. The foreign keys now null on delete and the terminal serial
 * is snapshotted so a retained event still names the hardware it came from.
 *
 * The unique `(device_id, event_fingerprint)` index on `biometric_event_receipts`
 * is deliberately kept: MySQL permits multiple NULL values in a unique index and
 * no further events arrive for a deleted terminal, so retained receipts cannot
 * collide.
 *
 * The `down()` migration is forward-only in practice: restoring the restrictive
 * foreign keys would require deleting (or re-binding) rows whose device no longer
 * exists, which destroys audit evidence. It therefore refuses rather than
 * silently discarding those rows.
 *
 * Every DDL step is guarded. MySQL DDL is non-transactional, so a run that fails
 * after `dropForeign` would otherwise leave the table without its key and make
 * the migration impossible to re-run; the guards let it resume instead.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('door_events', 'device_serial')) {
            Schema::table('door_events', function (Blueprint $table) {
                $table->string('device_serial', 255)->nullable()->after('device_id');
            });
        }

        $this->backfillDoorEventSerials();
        if ($this->hasForeignKeyOn('door_events', 'device_id')) {
            Schema::table('door_events', function (Blueprint $table) {
                $table->dropForeign(['device_id']);
            });
        }

        Schema::table('door_events', function (Blueprint $table) {
            $table->unsignedBigInteger('device_id')->nullable()->change();
        });

        if (! $this->hasForeignKeyOn('door_events', 'device_id')) {
            Schema::table('door_events', function (Blueprint $table) {
                $table->foreign('device_id')->references('id')->on('biometric_devices')->nullOnDelete();
            });
        }

        if ($this->hasForeignKeyOn('biometric_event_receipts', 'device_id')) {
            Schema::table('biometric_event_receipts', function (Blueprint $table) {
                $table->dropForeign(['device_id']);
            });
        }

        Schema::table('biometric_event_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('device_id')->nullable()->change();
        });

        if (! $this->hasForeignKeyOn('biometric_event_receipts', 'device_id')) {
            Schema::table('biometric_event_receipts', function (Blueprint $table) {
                $table->foreign('device_id')->references('id')->on('biometric_devices')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (
            DB::table('door_events')->whereNull('device_id')->exists()
            || DB::table('biometric_event_receipts')->whereNull('device_id')->exists()
        ) {
            throw new RuntimeException(
                'Cannot restore the restrictive device_id foreign keys: access-control audit rows whose device was deleted must be retained. '
                . 'Re-bind or archive those rows deliberately before rolling back; cascade deletion would destroy audit evidence.'
            );
        }

        if ($this->hasForeignKeyOn('door_events', 'device_id')) {
            Schema::table('door_events', function (Blueprint $table) {
                $table->dropForeign(['device_id']);
            });
        }

        Schema::table('door_events', function (Blueprint $table) {
            $table->unsignedBigInteger('device_id')->nullable(false)->change();
        });

        if (! $this->hasForeignKeyOn('door_events', 'device_id')) {
            Schema::table('door_events', function (Blueprint $table) {
                $table->foreign('device_id')->references('id')->on('biometric_devices')->cascadeOnDelete();
            });
        }

        if ($this->hasForeignKeyOn('biometric_event_receipts', 'device_id')) {
            Schema::table('biometric_event_receipts', function (Blueprint $table) {
                $table->dropForeign(['device_id']);
            });
        }

        Schema::table('biometric_event_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('device_id')->nullable(false)->change();
        });

        if (! $this->hasForeignKeyOn('biometric_event_receipts', 'device_id')) {
            Schema::table('biometric_event_receipts', function (Blueprint $table) {
                $table->foreign('device_id')->references('id')->on('biometric_devices')->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('door_events', 'device_serial')) {
            Schema::table('door_events', function (Blueprint $table) {
                $table->dropColumn('device_serial');
            });
        }
    }

    /**
     * Populate `door_events.device_serial` for rows written before the snapshot existed.
     *
     * Kept separate from `up()` so the backfill can be asserted against seeded rows without
     * replaying the migration's DDL against the shared test schema.
     */
    private function backfillDoorEventSerials(): void
    {
        DB::statement(
            'UPDATE door_events e INNER JOIN biometric_devices d ON d.id = e.device_id
             SET e.device_serial = d.device_serial
             WHERE e.device_serial IS NULL'
        );
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
