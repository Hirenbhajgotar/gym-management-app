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
        Schema::table('payments', function (Blueprint $table) {
            // Drop existing foreign key constraint for gym_id to make it nullable
            $table->dropForeign(['gym_id']);
            $table->unsignedBigInteger('gym_id')->nullable()->change();
            $table->foreign('gym_id')->references('id')->on('gyms')->onDelete('cascade');

            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->onDelete('cascade');

            $table->index(['user_id']);
            $table->index(['gym_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['gym_id']);
            $table->dropColumn('user_id');

            // Restore gym_id to not nullable
            $table->dropForeign(['gym_id']);
            $table->unsignedBigInteger('gym_id')->nullable(false)->change();
            $table->foreign('gym_id')->references('id')->on('gyms')->onDelete('cascade');
        });
    }
};
