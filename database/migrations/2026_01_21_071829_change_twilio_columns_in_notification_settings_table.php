<?php

declare(strict_types=1);

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
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->text('twilio_account_sid')->nullable()->change();
            $table->text('twilio_auth_token')->nullable()->change();
            $table->text('waba_sid')->nullable()->after('twilio_auth_token');
            $table->text('whatsapp_sender_sid')->nullable()->after('waba_sid');
            $table->text('phone_number_sid')->nullable()->after('whatsapp_sender_sid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->string('twilio_account_sid')->nullable()->change();
            $table->string('twilio_auth_token')->nullable()->change();
            $table->dropColumn(['waba_sid', 'whatsapp_sender_sid', 'phone_number_sid']);
        });
    }
};
