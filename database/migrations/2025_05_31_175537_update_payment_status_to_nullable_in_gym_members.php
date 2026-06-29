<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing payment_status values from numeric to string
        DB::table('gym_members')
            ->where('payment_status', '0')
            ->update(['payment_status' => 'pending']);

        DB::table('gym_members')
            ->where('payment_status', '1')
            ->update(['payment_status' => 'completed']);

        DB::table('gym_members')
            ->where('payment_status', '2')
            ->update(['payment_status' => 'failed']);

        DB::table('gym_members')
            ->where('payment_status', '3')
            ->update(['payment_status' => 'expired']);

        // Optional: Set a default value for any unexpected values
        DB::table('gym_members')
            ->whereNotIn('payment_status', ['pending', 'completed', 'failed', 'expired'])
            ->update(['payment_status' => 'pending']);

        Schema::table('gym_members', function (Blueprint $table) {
            $table->string('payment_status')->default('pending')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the payment_status values back to numeric (if needed)
        // DB::table('gym_members')
        //     ->where('payment_status', 'pending')
        //     ->update(['payment_status' => '0']);

        // DB::table('gym_members')
        //     ->where('payment_status', 'completed')
        //     ->update(['payment_status' => '1']);

        // DB::table('gym_members')
        //     ->where('payment_status', 'failed')
        //     ->update(['payment_status' => '2']);

        // DB::table('gym_members')
        //     ->where('payment_status', 'expired')
        //     ->update(['payment_status' => '3']);

        Schema::table('gym_members', function (Blueprint $table) {
            $table->string('payment_status')->nullable(false)->change();
        });
    }
};
