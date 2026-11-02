<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Preserve the highest delivery count a command ever reached.
 *
 * `attempt_count` is the *current* delivery budget: the reconciler re-drives a
 * dead-lettered `user_delete` with a fresh epoch and an operator resend resets it
 * to zero. `prunable()` deliberately retains rows at/above the cap because they
 * are the only durable evidence that a GDPR erasure was attempted and abandoned,
 * so a reset silently destroyed that evidence and made the row prunable.
 *
 * `max_attempt_count` is the high-water mark and is never lowered. The backfill
 * seeds it from `attempt_count`, so existing dead-lettered rows keep their
 * retention guarantee from the moment this migration runs.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('adms_device_commands', 'max_attempt_count')) {
            Schema::table('adms_device_commands', function (Blueprint $table) {
                $table->unsignedInteger('max_attempt_count')->default(0)->after('attempt_count');
            });
        }

        // Guarded DML: re-running a partially applied migration must never lower a
        // high-water mark above the current counter. The docblock promises the value is
        // never lowered, and an unconditional backfill would silently destroy the retention
        // evidence on the second run.
        DB::table('adms_device_commands')
            ->whereColumn('max_attempt_count', '<', 'attempt_count')
            ->update([
                'max_attempt_count' => DB::raw('COALESCE(attempt_count, 0)'),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('adms_device_commands', 'max_attempt_count')) {
            Schema::table('adms_device_commands', function (Blueprint $table) {
                $table->dropColumn('max_attempt_count');
            });
        }
    }
};
