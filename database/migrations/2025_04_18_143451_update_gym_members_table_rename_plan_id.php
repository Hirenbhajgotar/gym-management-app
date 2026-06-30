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
        Schema::table('gym_members', function (Blueprint $table) {
            $table->renameColumn('plan_id', 'membership_plan_id');
            $table->foreign('membership_plan_id')
                ->references('id')
                ->on('membership_plans')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gym_members', function (Blueprint $table) {
            $table->renameColumn('membership_plan_id', 'plan_id');
            $table->foreign('plan_id')
                ->references('id')
                ->on('plans')
                ->onDelete('set null');
        });
    }
};
