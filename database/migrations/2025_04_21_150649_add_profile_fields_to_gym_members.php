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
            $table->string('profile_photo')->nullable()->after('name');
            $table->decimal('weight', 5, 2)->nullable()->after('address')->comment('Weight in kg'); // Weight in kg
            $table->decimal('height', 5, 2)->nullable()->after('weight')->comment('Height in cm'); // Height in cm
            $table->unsignedTinyInteger('age')->nullable()->after('height');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gym_members', function (Blueprint $table) {
            Schema::table('gym_members', function (Blueprint $table) {
                $table->dropColumn(['profile_photo', 'weight', 'height', 'age']);
            });
        });
    }
};
