<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('door_status')->nullable()->after('denial_reason');
            // unlocked | locked | failed | skipped | not_applicable

            $table->string('door_denial_reason')->nullable()->after('door_status');
            // membership_expired | payment_pending | membership_frozen | verification_failed

            $table->timestamp('door_unlocked_at')->nullable()->after('door_denial_reason');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'door_status',
                'door_denial_reason',
                'door_unlocked_at',
            ]);
        });
    }
};
