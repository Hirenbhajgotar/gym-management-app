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
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->dropUnique('whatsapp_phone_numbers_phone_number_sid_unique');
            $table->string('phone_number_sid')->nullable()->unique()->change();
            $table->unique(['whatsapp_account_id', 'waba_phone_number_id'], 'wa_acc_waba_phone_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
            // Rolling back this migration would destroy Meta WABA phone number records
            // that legitimately have NULL phone_number_sid.
            // If rollback is absolutely required, manually handle data migration first.
            throw new \RuntimeException(
                'This migration cannot be safely rolled back. Meta WABA records with NULL phone_number_sid would be deleted.'
            );
        });
    }
};
