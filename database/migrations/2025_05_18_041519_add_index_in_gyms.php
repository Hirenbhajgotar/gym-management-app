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
            $table->index('name');
        });

        Schema::table('membership_plans', function (Blueprint $table) {
            $table->index('is_active');
        });

        Schema::table('member_payments', function (Blueprint $table) {
            $table->index('status');
            $table->index('payment_method');
            $table->index('payment_date');
        });

        Schema::table('trainers', function (Blueprint $table) {
            $table->index('name');
            $table->index('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gym_members', function (Blueprint $table) {
            // $table->dropIndex(['email']);
            $table->dropIndex(['name']);
        });

        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });

        Schema::table('member_payments', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['payment_method']);
            $table->dropIndex(['payment_date']);
        });

        Schema::table('trainers', function (Blueprint $table) {
            $table->dropIndex(['name']);
            // $table->dropIndex(['email']);
            $table->dropIndex(['currency']);
        });
    }
};
