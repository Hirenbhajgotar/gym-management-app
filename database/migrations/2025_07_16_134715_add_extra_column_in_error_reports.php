<?php

declare(strict_types=1);

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
        Schema::table('error_reports', function (Blueprint $table) {
            $table->string('error_hash')->nullable()->after('error_message'); // For duplicate detection
            $table->integer('duplicate_count')->default(1)->after('priority'); // Track how many times this error occurred
            $table->timestamp('last_occurrence')->nullable()->after('duplicate_count'); // Last time this error occurred

            $table->index(['error_hash', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('error_reports', function (Blueprint $table) {
            $table->dropIndex(['error_hash', 'created_at']);
            $table->dropColumn([
                'error_hash',
                'duplicate_count',
                'last_occurrence',
            ]);
        });
    }
};
