<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Add offline_id column for idempotent sync
            $table->string('offline_id', 255)->nullable()->after('device_id');

            // Add unique index on (device_id, offline_id) to enforce uniqueness
            // This prevents duplicate records from retry attempts
            $table->unique(['device_id', 'offline_id'], 'attendances_device_offline_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('attendances_device_offline_unique');
            $table->dropColumn('offline_id');
        });
    }
};
