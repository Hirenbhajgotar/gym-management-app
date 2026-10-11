<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_event_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('biometric_devices')->cascadeOnDelete();
            $table->string('event_fingerprint', 64);
            $table->string('status')->default('processed'); // processed, ignored, failed
            $table->foreignId('attendance_id')->nullable()->constrained('attendances')->nullOnDelete();
            $table->json('payload_summary')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'event_fingerprint']);
            $table->index(['gym_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_event_receipts');
    }
};
