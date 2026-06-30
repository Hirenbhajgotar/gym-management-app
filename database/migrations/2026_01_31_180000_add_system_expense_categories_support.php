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
        Schema::table('expense_categories', function (Blueprint $table) {
            // Drop the existing foreign key constraint on gym_id
            $table->dropForeign(['gym_id']);

            // Make gym_id nullable (NULL = system default category)
            $table->foreignId('gym_id')->nullable()->change();

            // Add is_system flag to mark system-wide default categories
            $table->boolean('is_system')->default(false)->after('is_active');

            // Re-add the foreign key but allow NULL
            $table->foreign('gym_id')
                ->references('id')
                ->on('gyms')
                ->nullOnDelete();

            // Add index for efficient querying of system defaults
            $table->index(['is_system', 'is_active'], 'expense_categories_system_active_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropIndex('expense_categories_system_active_index');
            $table->dropForeign(['gym_id']);
            $table->dropColumn('is_system');

            // Restore NOT NULL constraint (would fail if system categories exist)
            $table->foreignId('gym_id')->nullable(false)->change();
            $table->foreign('gym_id')
                ->references('id')
                ->on('gyms')
                ->cascadeOnDelete();
        });
    }
};
