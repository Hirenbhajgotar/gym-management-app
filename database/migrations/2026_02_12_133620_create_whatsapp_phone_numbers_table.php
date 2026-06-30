<?php

declare(strict_types=1);

use App\Enums\WhatsAppPhoneNumberStatusEnum;
use App\Enums\WhatsAppPhoneNumberTypesEnum;
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
        Schema::create('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_account_id')->nullable()->constrained('whatsapp_accounts')->onDelete('cascade');

            $table->string('phone_number')->unique();
            $table->string('phone_number_sid')->unique();
            $table->string('country_code', 2);

            $table->string('friendly_name')->nullable();
            $table->string('locality')->nullable();
            $table->string('region')->nullable();

            $table->decimal('monthly_cost', 10, 2)->default(0);
            $table->decimal('number_cost', 10, 2)->default(0);
            $table->string('status')->default(WhatsAppPhoneNumberStatusEnum::PURCHASED->value);
            $table->string('type')->default(WhatsAppPhoneNumberTypesEnum::LOCAL->value);

            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_phone_numbers');
    }
};
