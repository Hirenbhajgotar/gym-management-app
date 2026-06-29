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
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
            // WABA-specific: stores the phone number ID from Meta's Graph API
            // This is different from Twilio's phone_number_sid
            $table->string('waba_phone_number_id', 64)
                ->nullable()
                ->after('channel_sender_sid')
                ->comment('Meta WABA phone number ID for WABA provider');

            // Index for WABA provider lookups
            $table->index('waba_phone_number_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->dropIndex(['waba_phone_number_id']);
            $table->dropColumn('waba_phone_number_id');
        });
    }
};
