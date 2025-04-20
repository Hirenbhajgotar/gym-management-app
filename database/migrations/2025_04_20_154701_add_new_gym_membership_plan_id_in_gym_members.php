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
            $table->unsignedBigInteger('gym_membership_plan_id')->nullable()->after('membership_end');
            $table->foreign('gym_membership_plan_id')->references('id')->on('membership_plans');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gym_members', function (Blueprint $table) {
            $table->dropForeign(['gym_membership_plan_id']);
            $table->dropColumn(['gym_membership_plan_id']);
        });
    }
};
