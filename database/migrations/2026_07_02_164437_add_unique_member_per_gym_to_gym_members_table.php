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
            // Add composite unique key for idempotent imports
            // A member is uniquely identified by gym + email + phone combination
            $table->unique(['gym_id', 'email', 'phone'], 'unique_member_per_gym');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gym_members', function (Blueprint $table) {
            $table->dropUnique('unique_member_per_gym');
        });
    }
};
