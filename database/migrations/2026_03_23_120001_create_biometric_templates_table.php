<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_member_id')->constrained('gym_members')->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrolled_by_device_id')->nullable()->constrained('biometric_devices');
            $table->string('biometric_type'); // face | fingerprint | iris
            $table->string('provider_type');
            $table->text('template_ref')->nullable(); // encrypted — provider-side ID only, NEVER raw biometric
            $table->float('quality_score')->nullable(); // 0.0 - 1.0
            $table->boolean('is_active')->default(true);
            $table->timestamp('enrolled_at');
            $table->timestamps();

            $table->index(['gym_member_id', 'gym_id', 'biometric_type', 'is_active'], 'biometric_templates_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_templates');
    }
};
