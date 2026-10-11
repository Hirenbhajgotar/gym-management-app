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
            $table->timestamp('enrolled_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rolling back nullability of enrolled_at is irreversible because Pending placeholders lack an enrolled_at timestamp.');
    }
};
