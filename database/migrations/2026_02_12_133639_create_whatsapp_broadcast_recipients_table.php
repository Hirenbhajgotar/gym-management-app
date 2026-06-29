<?php

declare(strict_types=1);

use App\Enums\WhatsAppBroadcastRecipientStatusEnum;
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
        Schema::create('whatsapp_broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_broadcast_id')->constrained('whatsapp_broadcasts')->onDelete('cascade');
            $table->foreignId('member_id')->constrained('gym_members')->onDelete('cascade');

            $table->string('phone_number', 20);
            $table->string('member_name', 255);

            $table->string('status')->default(WhatsAppBroadcastRecipientStatusEnum::PENDING->value)->nullable();
            $table->string('twilio_message_sid')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();

            $table->index(['whatsapp_broadcast_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_broadcast_recipients');
    }
};
