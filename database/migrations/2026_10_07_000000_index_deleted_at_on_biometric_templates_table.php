<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // Every query on this model carries the SoftDeletes `deleted_at IS NULL` predicate.
        Schema::table('biometric_templates', function (Blueprint $table) {
            $table->index('deleted_at', 'biometric_templates_deleted_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_templates', function (Blueprint $table) {
            $table->dropIndex('biometric_templates_deleted_at_index');
        });
    }
};
