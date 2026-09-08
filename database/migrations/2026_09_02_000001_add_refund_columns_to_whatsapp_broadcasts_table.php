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
        Schema::table('whatsapp_broadcasts', function (Blueprint $table) {
            $table->boolean('credits_deducted_upfront')->default(false)->after('credits_used');
            $table->integer('refunded_credits')->default(0)->after('credits_deducted_upfront');
            $table->timestamp('refunded_at')->nullable()->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_broadcasts', function (Blueprint $table) {
            $table->dropColumn(['credits_deducted_upfront', 'refunded_credits', 'refunded_at']);
        });
    }
};
