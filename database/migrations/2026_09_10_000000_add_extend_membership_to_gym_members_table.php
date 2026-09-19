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
            $table->boolean('extend_membership')->default(false)->after('frozen_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gym_members', function (Blueprint $table) {
            $table->dropColumn(['extend_membership']);
        });
    }
};
