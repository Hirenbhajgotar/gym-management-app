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
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->onDelete('cascade');
            $table->foreignId('gym_owner_id')->nullable()->constrained('users');
            $table->string('whatsapp_business_number')->nullable();
            $table->string('whatsapp_api_key')->nullable();
            $table->string('whatsapp_instance_id')->nullable();
            $table->string('whatsapp_provider')->nullable()->default('twilio'); // twilio, ultramsg, etc.
            $table->json('notification_types')->nullable(); // stores enabled notification types
            $table->string('twilio_account_sid')->nullable();
            $table->string('twilio_auth_token')->nullable();
            $table->string('twilio_api_key')->nullable();
            $table->string('twilio_api_secret')->nullable();
            $table->string('account_status')->nullable()->default('pending');
            $table->string('verification_status')->nullable()->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->json('twilio_metadata')->nullable(); // Store additional Twilio data
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
