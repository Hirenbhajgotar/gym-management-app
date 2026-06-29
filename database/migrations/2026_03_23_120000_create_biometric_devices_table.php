<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('device_name');
            $table->string('device_type'); // fingerprint | face | iris | card_bio | mobile
            $table->string('provider_type'); // rekognition | azure_face | bioid | on_premise | custom
            $table->text('provider_config')->nullable(); // encrypted JSON
            $table->string('status')->default('active'); // active | inactive | maintenance
            $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('offline_capable')->default(false);
            $table->boolean('supports_multi_modal')->default(false);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('device_token')->nullable(); // stored as Hash::make($plainToken)
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['gym_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_devices');
    }
};
