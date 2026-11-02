<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make preserved erasure evidence self-describing and bound transient re-arms.
 *
 * `preserved_evidence_at` marks a delivered `user_add` that survives its terminal so
 * `wasDeliveredToHardware()` can still prove the credential reached flash. The marker is
 * what lets the reclaim sweep and `prunable()` distinguish it from a live in-flight row
 * without re-parsing the command type.
 *
 * `transient_rearm_count` bounds how many times a lease that expired at the delivery cap
 * may be re-armed on the "the terminal was offline, not rejecting" premise. Without a
 * bound, a terminal that polls but never ACKs keeps an erasure in `PendingDeletion`
 * forever with no operator surface.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('adms_device_commands', 'preserved_evidence_at')) {
            Schema::table('adms_device_commands', function (Blueprint $table): void {
                $table->timestamp('preserved_evidence_at')->nullable()->after('acknowledged_at');
            });
        }

        if (! Schema::hasColumn('adms_device_commands', 'transient_rearm_count')) {
            Schema::table('adms_device_commands', function (Blueprint $table): void {
                $table->unsignedSmallInteger('transient_rearm_count')->default(0)->after('max_attempt_count');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('adms_device_commands', 'transient_rearm_count')) {
            Schema::table('adms_device_commands', function (Blueprint $table): void {
                $table->dropColumn('transient_rearm_count');
            });
        }

        if (Schema::hasColumn('adms_device_commands', 'preserved_evidence_at')) {
            Schema::table('adms_device_commands', function (Blueprint $table): void {
                $table->dropColumn('preserved_evidence_at');
            });
        }
    }
};
