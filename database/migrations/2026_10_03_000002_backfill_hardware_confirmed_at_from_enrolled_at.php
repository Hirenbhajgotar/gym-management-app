<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    public function up(): void
    {
        // The literal 'synced' is used instead of the enum case on purpose: a data migration
        // must not silently change behaviour when a live enum is renamed, because once it has
        // run the historical evidence it repairs no longer exists to re-derive.
        DB::table('biometric_templates')
            ->where('sync_status', 'synced')
            ->whereNotNull('enrolled_at')
            ->whereNull('hardware_confirmed_at')
            ->update(['hardware_confirmed_at' => DB::raw('enrolled_at')]);
    }

    public function down(): void
    {
        // Irreversible backfill data migration
    }
};
