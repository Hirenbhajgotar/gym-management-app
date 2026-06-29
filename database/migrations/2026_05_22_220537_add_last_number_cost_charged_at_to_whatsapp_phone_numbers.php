<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->timestamp('last_number_cost_charged_at')->nullable()->after('purchased_at')->comment('Last time the phone number monthly cost was charged to the tenant');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->dropColumn('last_number_cost_charged_at');
        });
    }
};
