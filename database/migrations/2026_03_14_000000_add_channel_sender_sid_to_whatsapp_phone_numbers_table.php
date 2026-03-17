<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds channel_sender_sid column to store WhatsApp Channel Sender SID separately
     * from the IncomingPhoneNumber SID. This fixes the issue where phone_number_sid
     * was incorrectly storing different Twilio SID types.
     */
    public function up(): void
    {
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->string('channel_sender_sid', 64)->nullable()->after('phone_number_sid')->comment('WhatsApp Channel Sender SID (starts with CN)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->dropColumn('channel_sender_sid');
        });
    }
};
