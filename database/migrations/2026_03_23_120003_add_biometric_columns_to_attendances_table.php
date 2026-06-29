<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('device_id')
                ->nullable()
                ->after('note')
                ->constrained('biometric_devices')
                ->nullOnDelete();

            $table->string('check_in_method')
                ->nullable()
                ->after('device_id')
                ->default(null); // null = manual (backward compat) | biometric | card | mobile

            $table->string('biometric_type')->nullable()->after('check_in_method'); // face|fingerprint|iris
            $table->string('provider_type')->nullable()->after('biometric_type');
            $table->float('confidence_score')->nullable()->after('provider_type');
            $table->float('liveness_score')->nullable()->after('confidence_score');
            $table->float('fusion_score')->nullable()->after('liveness_score');
            $table->string('check_in_result')->nullable()->after('fusion_score'); // granted|denied|suspicious
            $table->string('denial_reason')->nullable()->after('check_in_result');
            $table->timestamp('synced_at')->nullable()->after('denial_reason'); // null = real-time
            $table->string('check_in_ip')->nullable()->after('synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['device_id']);
            $table->dropColumn([
                'device_id',
                'check_in_method',
                'biometric_type',
                'provider_type',
                'confidence_score',
                'liveness_score',
                'fusion_score',
                'check_in_result',
                'denial_reason',
                'synced_at',
                'check_in_ip',
            ]);
        });
    }
};
