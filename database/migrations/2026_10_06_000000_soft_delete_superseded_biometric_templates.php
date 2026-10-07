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
            $table->string('superseded_reason', 64)->nullable()->after('sync_status');
        });

        Schema::table('biometric_templates', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('biometric_templates', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('biometric_templates', function (Blueprint $table) {
            $table->dropColumn('superseded_reason');
        });
    }
};
