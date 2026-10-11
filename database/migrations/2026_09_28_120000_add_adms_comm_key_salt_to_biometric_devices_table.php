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
            $table->string('adms_comm_key_hash', 128)->nullable()->change();
            $table->string('adms_comm_key_salt', 64)->nullable()->after('adms_comm_key_hash');
        });
    }

    public function down(): void
    {
        throw new \RuntimeException('Biometric device communication key salt migration is irreversible.');
    }
};
