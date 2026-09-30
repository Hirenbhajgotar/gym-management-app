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
            $table->string('provider_type')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Reversing nullable provider_type is irreversible without data loss because native devices
        // legitimately store NULL provider_type. Converting NULL to 'on_premise' corrupts device-native runtime logic.
        throw new RuntimeException('Reversing nullable provider_type is irreversible without database backup restoration, as device-native devices require NULL provider_type.');
    }
};
