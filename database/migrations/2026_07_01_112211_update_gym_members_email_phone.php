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
        DB::table('gym_members')
            ->whereNull('phone')
            ->update(['phone' => DB::raw("CONCAT('0000000000', id)")]);

        Schema::table('gym_members', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('phone')->nullable(false)->change();
            $table->unique(['gym_id', 'phone']);
            $table->index('phone');

            if (Schema::hasIndex('gym_members', 'gym_members_email_unique')) {
                $table->dropUnique('gym_members_email_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gym_members', function (Blueprint $table) {
            $table->dropUnique(['gym_id', 'phone']);
            $table->dropIndex('gym_members_phone_index');
            $table->string('email')->nullable(false)->change();
            $table->unique('email');
            $table->string('phone')->nullable()->change();
        });
    }
};
