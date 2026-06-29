<?php

declare(strict_types=1);

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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users'); // recipient
            $table->string('type')->nullable(); // 'joining', 'birthday', etc.
            $table->string('channel')->nullable(); // 'email', 'whatsapp'
            $table->string('status')->nullable(); // 'sent', 'failed', 'pending'
            $table->text('message')->nullable();
            $table->json('response_data')->nullable();
            $table->string('external_id')->nullable(); // provider's message ID
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
