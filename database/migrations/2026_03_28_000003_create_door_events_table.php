<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('door_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('biometric_devices')->cascadeOnDelete();
            $table->foreignId('gym_member_id')->nullable()->constrained('gym_members')->nullOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained('attendances')->nullOnDelete();

            $table->string('event_type');
            // unlock_granted | unlock_denied | unlock_failed | lock_confirmed

            $table->string('trigger_source')->default('biometric');
            // biometric | manual_staff | emergency

            $table->string('door_status');
            // unlocked | locked | failed | skipped

            $table->string('denial_reason')->nullable();
            $table->integer('unlock_duration_seconds')->nullable();
            $table->timestamp('unlocked_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'created_at']);
            $table->index(['gym_member_id', 'created_at']);
            $table->index(['device_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('door_events');
    }
};
