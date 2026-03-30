<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_member_id')->constrained('gym_members')->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address');
            $table->string('version');
            $table->timestamp('consented_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            // Partial unique index: only one active consent per member/gym
            // Allows multiple rows over time but only one active consent at a time
            $table->unique(['gym_member_id', 'gym_id'], 'biometric_consents_active_unique')
                ->whereNull('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_consents');
    }
};
