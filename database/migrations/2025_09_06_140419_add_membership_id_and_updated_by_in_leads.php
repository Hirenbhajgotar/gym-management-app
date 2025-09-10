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
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('membership_plan_id')->nullable()->after('gym_id');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');

            $table->foreign('membership_plan_id')->references('id')->on('membership_plans')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['membership_plan_id', 'updated_by']);
            $table->dropColumn(['membership_plan_id', 'updated_by']);
        });
    }
};
