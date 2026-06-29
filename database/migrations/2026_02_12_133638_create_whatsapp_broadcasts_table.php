<?php

declare(strict_types=1);

use App\Enums\WhatsAppBroadcastStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_template_id')->constrained('whatsapp_templates')->onDelete('cascade');

            $table->string('title', 255);
            $table->text('description')->nullable();

            $table->json('target_filters')->nullable();
            $table->integer('target_count')->default(0);

            $table->string('status')->default(WhatsAppBroadcastStatusEnum::DRAFT->value)->nullable();

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('credits_used')->default(0);

            $table->json('message_variables')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_broadcasts');
    }
};
