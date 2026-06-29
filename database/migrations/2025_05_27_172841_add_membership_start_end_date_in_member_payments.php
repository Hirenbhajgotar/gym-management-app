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
        Schema::table('member_payments', function (Blueprint $table) {
            $table->date('membership_start')
                ->nullable()
                ->after('payment_date');
            $table->date('membership_end')
                ->nullable()
                ->after('membership_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_payments', function (Blueprint $table) {
            $table->dropColumn(['membership_start', 'membership_end']);
        });
    }
};
