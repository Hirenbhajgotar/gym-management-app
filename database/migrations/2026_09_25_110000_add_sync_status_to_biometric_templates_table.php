<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_templates', function (Blueprint $table) {
            $table->string('sync_status', 32)->default('synced')->after('is_active');
            $table->index(['gym_id', 'sync_status'], 'biometric_templates_sync_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_templates', function (Blueprint $table) {
            $table->dropIndex('biometric_templates_sync_status_idx');
            $table->dropColumn('sync_status');
        });
    }
};
