<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Record the `user_add` command id credited as starting a fresh erasure epoch.
     *
     * A PIN-scoped `user_delete` is re-driven in place, so its `created_at` never advances and
     * a single re-enrolment's `user_add` satisfied the old "newer credential" predicate for
     * the row's whole life: after one epoch reset, a second round of terminal rejections reset
     * `attempt_count` to zero again instead of exhausting the cap, so `Exhausted` and the
     * dead-letter signal were not durable. Recording the matched command id makes the
     * acknowledgement consumable exactly once, and ids are monotonic, so a second-precision
     * timestamp tie cannot hide a new epoch.
     */
    public function up(): void
    {
        Schema::table('adms_device_commands', function (Blueprint $table) {
            $table->unsignedBigInteger('epoch_credential_user_add_id')->nullable()->after('attempt_count');
        });
    }

    public function down(): void
    {
        Schema::table('adms_device_commands', function (Blueprint $table) {
            $table->dropColumn('epoch_credential_user_add_id');
        });
    }
};
