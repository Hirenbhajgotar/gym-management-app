<?php

declare(strict_types=1);

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
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
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->onDelete('cascade');

            $table->string('twilio_account_sid')->unique()->nullable();
            $table->string('twilio_auth_token')->nullable();
            $table->string('waba_id')->nullable();
            $table->string('messaging_service_sid')->nullable();

            $table->string('status')->default(WhatsAppAccountStatusEnum::PENDING->value)->nullable();
            $table->string('verification_status')->default(WhatsAppAccountVerificationStatusEnum::PENDING->value)->nullable();

            $table->string('business_name')->nullable();
            $table->string('business_email')->nullable();
            $table->text('business_description')->nullable();

            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_accounts');
    }
};
