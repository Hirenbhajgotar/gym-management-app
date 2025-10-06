<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->onDelete('cascade');
            $table->string('template_name'); // e.g., 'birthday_greeting'
            $table->string('template_sid'); // Twilio Content SID (HXxxx...)
            $table->string('language')->default('en')->nullable();
            $table->json('variables')->nullable(); // Template variable definitions
            $table->string('status')->default('pending')->nullable(); // e.g., 'approved', 'pending', 'rejected'
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
