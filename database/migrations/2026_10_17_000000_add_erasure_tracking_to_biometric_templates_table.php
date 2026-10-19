<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Track the two facts the erasure lifecycle was missing.
     *
     * `erasure_attempts` counts provider-side erase attempts for a cloud-backed row. A
     * provider that permanently refuses (a stale reference, a revoked API key) used to be
     * re-selected by the hourly reconciler forever with no cap, so the attempt budget is what
     * lets the row reach the terminal `DeletionUnresolvable` state and stop looping.
     *
     * `erasure_requested_at` records *why* a row is awaiting deletion. `PendingDeletion` is
     * the universal parked state (an archive, a sweep, the reconciler), so deriving the
     * `erasure_override` compliance reason from the status alone fabricated a GDPR Art. 17
     * withdrawal record for an ordinary machine park. The marker is written only by the
     * member's own erasure path (`BiometricDataDeletionService`).
     */
    public function up(): void
    {
        Schema::table('biometric_templates', function (Blueprint $table) {
            $table->unsignedInteger('erasure_attempts')->default(0)->after('is_erasure_obligation');
            $table->timestamp('erasure_requested_at')->nullable()->after('erasure_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_templates', function (Blueprint $table) {
            $table->dropColumn(['erasure_attempts', 'erasure_requested_at']);
        });
    }
};
