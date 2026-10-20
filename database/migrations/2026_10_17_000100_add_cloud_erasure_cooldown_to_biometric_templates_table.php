<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Record when a cloud-backed erasure obligation first failed, so its budget is measured
     * in time rather than in retry count.
     *
     * One `erasure_attempts` counter is shared by every writer that drives an erasure: the
     * hourly `biometrics:reconcile-pending-deletions` reconciler and the consent-revoked
     * queue listener (`$tries = 3`, 60/180/300s backoff). A raw count therefore lets the
     * listener spend the whole budget inside four minutes, turning a transient provider blip
     * into the terminal `DeletionUnresolvable` state — excluding the row from the reconciler
     * and leaving a possibly-live provider credential with no scheduled retry. Measuring the
     * budget from the first failure makes a burst of retries unable to exhaust it, and gives
     * the operator surface a stamp to reset when the provider is repaired.
     */
    public function up(): void
    {
        Schema::table('biometric_templates', function (Blueprint $table) {
            $table->timestamp('erasure_first_attempted_at')->nullable()->after('erasure_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_templates', function (Blueprint $table) {
            $table->dropColumn('erasure_first_attempted_at');
        });
    }
};
