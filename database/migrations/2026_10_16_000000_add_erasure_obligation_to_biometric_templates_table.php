<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Mark a template row as a machine-written erasure obligation rather than an
     * enrolment record.
     *
     * An orphan marker (see BiometricEnrollController::recordOrphanedRemoteTemplate())
     * records a remote credential a refused enrolment may have left behind. It is
     * parked in a deletion state so the reconciler can erase it, but it is NOT a
     * template a later enrolment replaces: superseding it would cancel the erasure
     * obligation and stamp a false GDPR-override audit line. This flag lets the
     * supersede scope and the sibling fan-out tell the two row kinds apart.
     */
    public function up(): void
    {
        Schema::table('biometric_templates', function (Blueprint $table) {
            $table->boolean('is_erasure_obligation')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_templates', function (Blueprint $table) {
            $table->dropColumn('is_erasure_obligation');
        });
    }
};
