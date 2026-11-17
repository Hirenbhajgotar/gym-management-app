<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE biometric_consents DROP INDEX biometric_consents_active_unique, ADD UNIQUE KEY biometric_consents_active_unique (gym_member_id, gym_id, ((CASE WHEN revoked_at IS NULL THEN 1 ELSE NULL END)))');
        }
    }

    /**
     * Reverse the migrations.
     *
     * Note: Re-applying the strict UNIQUE KEY (gym_member_id, gym_id) on rollback
     * will fail if any members have multiple historical consent rows (e.g. revoked + active).
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE biometric_consents DROP INDEX biometric_consents_active_unique, ADD UNIQUE KEY biometric_consents_active_unique (gym_member_id, gym_id)');
        }
    }
};
