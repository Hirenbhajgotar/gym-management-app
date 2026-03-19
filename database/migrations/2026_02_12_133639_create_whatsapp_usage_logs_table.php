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
        Schema::create('whatsapp_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_template_id')->nullable()->constrained('whatsapp_templates')->onDelete('set null');

            $table->string('twilio_message_sid')->unique()->nullable();
            $table->string('recipient');

            $table->integer('credits_used')->default(1)->nullable();
            $table->decimal('twilio_cost', 10, 4)->default(0)->nullable();

            $table->string('status')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();

            $table->index(['gym_id', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_usage_logs');
    }
};
