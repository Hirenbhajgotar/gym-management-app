<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot the terminal serial on retained webhook receipts.
 *
 * `2026_10_31_000300` nulls `biometric_event_receipts.device_id` instead of
 * cascading it away, but a receipt without a serial names only the gym after its
 * terminal is deleted — it can no longer say which hardware delivered the event.
 * `door_events` gained the same column in that migration; this one closes the
 * asymmetry so both halves of the access-control audit keep their attribution.
 *
 * Both statements are guarded: the column add only runs when it is absent, and
 * the backfill only touches rows that still have no snapshot, so a partially
 * applied run can be retried without duplicating work.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('biometric_event_receipts', 'device_serial')) {
            Schema::table('biometric_event_receipts', function (Blueprint $table) {
                $table->string('device_serial', 255)->nullable()->after('device_id');
            });
        }

        $this->backfillDeviceSerials();
    }

    /**
     * Populate `biometric_event_receipts.device_serial` from the still-linked device.
     *
     * Kept separate from `up()` so the backfill can be asserted against seeded rows without
     * replaying DDL against the shared test schema.
     */
    private function backfillDeviceSerials(): void
    {
        DB::statement(
            'UPDATE biometric_event_receipts r INNER JOIN biometric_devices d ON d.id = r.device_id
             SET r.device_serial = d.device_serial
             WHERE r.device_serial IS NULL'
        );
    }

    public function down(): void
    {
        if (Schema::hasColumn('biometric_event_receipts', 'device_serial')) {
            Schema::table('biometric_event_receipts', function (Blueprint $table) {
                $table->dropColumn('device_serial');
            });
        }
    }
};
