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
        Schema::table('whatsapp_message_credits', function (Blueprint $table) {
            $table->boolean('is_low_credit_notified')->default(false)->after('welcome_credits_claimed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_message_credits', function (Blueprint $table) {
            $table->dropColumn('is_low_credit_notified');
        });
    }
};
