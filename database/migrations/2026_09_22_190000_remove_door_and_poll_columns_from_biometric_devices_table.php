<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $columnsToDrop = [
                'door_control_type',
                'door_controller_url',
                'door_controller_token',
                'unlock_duration_seconds',
                'door_zone',
                'connection_mode',
                'poll_interval_seconds',
                'last_polled_at',
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('biometric_devices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Reversing dropped door control and polling columns is irreversible without database backup restoration, as dropped historical configurations, encrypted credentials, and poll timestamps cannot be safely recovered.');
    }
};
