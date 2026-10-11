<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->string('device_serial')->nullable()->after('device_name');
            $table->boolean('is_enabled')->default(true)->after('status');
            $table->boolean('adms_enabled')->default(false)->after('purpose');

            $table->unique('device_serial', 'unique_biometric_device_serial');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->dropUnique('unique_biometric_device_serial');
            $table->dropColumn(['device_serial', 'is_enabled', 'adms_enabled']);
        });
    }
};
