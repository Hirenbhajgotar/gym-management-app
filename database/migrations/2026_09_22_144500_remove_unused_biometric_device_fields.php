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
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->dropColumn([
                'firmware_version',
                'supports_multi_modal',
                'offline_capable',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('biometric_devices', 'offline_capable')) {
                $table->boolean('offline_capable')->default(false);
            }
            if (! Schema::hasColumn('biometric_devices', 'supports_multi_modal')) {
                $table->boolean('supports_multi_modal')->default(false);
            }
            if (! Schema::hasColumn('biometric_devices', 'firmware_version')) {
                $table->string('firmware_version')->nullable();
            }
        });
    }
};
