<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clear stale erasure-obligation flags from rows that are already `Deleted`.
 *
 * Round 3's cloud erase path wrote `Deleted` without clearing `is_erasure_obligation`, and
 * a hardware `user_delete` ACK did the same. Those rows are genuinely erased — `Deleted` is
 * only written by a confirmed provider erase, a confirmed terminal ACK, a proven
 * non-delivery, or an operator's physical-wipe attestation — so the flag is stale and left
 * them permanently listed in "Needs Operator" with no action that could clear it.
 *
 * The runtime paths now clear the flag in the same write as `Deleted`
 * ({@see \App\Models\BiometricTemplate::markErasureDischarged()}); this migration repairs
 * the cohort produced before that fix. It is intentionally idempotent and forward-only:
 * `down()` does not restore the flag, because doing so would recreate a false obligation
 * for a credential that is already gone.
 */
return new class() extends Migration
{
    public function up(): void
    {
        DB::table('biometric_templates')
            ->where('sync_status', 'deleted')
            ->where('is_erasure_obligation', true)
            ->update(['is_erasure_obligation' => false]);
    }

    public function down(): void
    {
        // Forward-only: restoring the flag would assert an outstanding erasure for a row
        // whose credential has already been confirmed erased.
    }
};
