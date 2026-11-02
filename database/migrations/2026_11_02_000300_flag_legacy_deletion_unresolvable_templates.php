<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the obligation flag on legacy `DeletionUnresolvable` rows.
 *
 * The guard used to read only `is_erasure_obligation`, so a row parked at
 * `DeletionUnresolvable` before that flag existed was invisible to the tenant-delete
 * guard and the operator surface. `requiresOperatorAttention()` already treats the state
 * as an obligation; this forward-only migration makes the durable column agree with that
 * predicate so no writer has to keep compensating for the legacy cohort.
 */
return new class() extends Migration
{
    public function up(): void
    {
        DB::table('biometric_templates')
            ->where('sync_status', 'deletion_unresolvable')
            ->where(function ($query): void {
                $query->whereNull('is_erasure_obligation')
                    ->orWhere('is_erasure_obligation', false);
            })
            ->update(['is_erasure_obligation' => true]);
    }

    public function down(): void
    {
        // Forward-only: clearing the flag would hide a legacy obligation from the guard
        // again. There is no way to distinguish a backfilled row from one parked after
        // the flag existed.
    }
};
