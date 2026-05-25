<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_credit_transactions', function (Blueprint $table) {
            $table->decimal('phone_number_surcharge', 10, 2)->nullable()->after('amount')->default(0.00)->comment('Twilio phone number monthly cost surcharge included in this purchase');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_credit_transactions', function (Blueprint $table) {
            $table->dropColumn('phone_number_surcharge');
        });
    }
};
