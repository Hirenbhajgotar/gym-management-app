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
            $table->boolean('is_frozen')->default(false)->after('user_id');
            $table->timestamp('frozen_at')->nullable()->after('is_frozen');
            $table->timestamp('frozen_until')->nullable()->after('frozen_at');
            $table->string('frozen_reason')->nullable()->after('frozen_until');
            $table->foreignId('frozen_by')->nullable()->constrained('users')->after('frozen_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gym_members', function (Blueprint $table) {
            $table->dropForeign(['frozen_by']);
            $table->dropColumn(['is_frozen', 'frozen_at', 'frozen_until', 'frozen_reason', 'frozen_by']);
        });
    }
};
