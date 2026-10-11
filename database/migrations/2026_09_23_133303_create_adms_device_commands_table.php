<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('adms_device_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('biometric_devices')->cascadeOnDelete();
            $table->string('command_type'); // user_add, user_delete, user_update, reboot, clear_log
            $table->json('command_payload')->nullable();
            $table->string('status')->default('pending'); // pending, sent, acknowledged, failed
            $table->string('idempotency_key')->nullable()->unique();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('leased_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('device_return_code')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'status', 'created_at']);
            $table->index(['gym_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adms_device_commands');
    }
};
