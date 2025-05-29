<?php

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
        Schema::table('gym_members', function (Blueprint $table) {
            $table->integer('payment_status')
                ->default(0)
                ->after('membership_end')
                ->index()
                ->comment('Payment status: 0 = pending, 1 = completed, 2 = failed, 3 = expired');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gym_members', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });
    }
};
