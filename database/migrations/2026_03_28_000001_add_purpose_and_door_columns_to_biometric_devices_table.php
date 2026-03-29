<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            // PRIMARY PURPOSE — drives all logic
            $table->string('purpose')->default('attendance_only')->after('status');
            // attendance_only        = biometric check-in, attendance only
            // door_access            = door unlock + attendance
            // door_access_no_attendance = door unlock only, no attendance

            // DOOR CONTROL — only relevant when purpose != attendance_only
            $table->string('door_control_type')->nullable()->after('purpose');
            // relay   = device built-in relay, device handles door on webhook response
            // wiegand = device sends Wiegand signal to access controller
            // api     = separate door controller with REST API

            $table->string('door_controller_url')->nullable()->after('door_control_type');
            // Only when door_control_type = api

            $table->string('door_controller_token')->nullable()->after('door_controller_url');
            // Encrypted — auth token for door controller API

            $table->integer('unlock_duration_seconds')->default(5)->after('door_controller_token');
            // How many seconds door stays unlocked

            $table->string('door_zone')->nullable()->after('unlock_duration_seconds');
            // Label for this door: main_entrance | gym_floor | pool | locker_room

            // CONNECTION MODE
            $table->string('connection_mode')->default('webhook')->after('door_zone');
            // webhook = device POSTs to FluxFitness
            // poll    = FluxFitness polls device API

            $table->integer('poll_interval_seconds')->default(60)->after('connection_mode');
            $table->timestamp('last_polled_at')->nullable()->after('poll_interval_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->dropColumn([
                'purpose',
                'door_control_type',
                'door_controller_url',
                'door_controller_token',
                'unlock_duration_seconds',
                'door_zone',
                'connection_mode',
                'poll_interval_seconds',
                'last_polled_at',
            ]);
        });
    }
};
