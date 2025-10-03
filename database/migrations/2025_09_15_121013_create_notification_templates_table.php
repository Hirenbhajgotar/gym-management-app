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
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->onDelete('cascade');
            $table->foreignId('gym_owner_id')->nullable()->constrained('users');
            $table->string('type')->nullable(); // 'joining', 'birthday', 'payment_reminder', etc.
            $table->string('channel')->nullable(); // 'email', 'whatsapp', 'both'
            $table->string('subject')->nullable(); // for email
            $table->text('email_template')->nullable();
            $table->text('whatsapp_template')->nullable();
            $table->boolean('is_active')->nullable()->default(true);
            $table->timestamps();

            $table->index(['type', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
