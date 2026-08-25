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
        Schema::table('error_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('error_reports', 'markdown_report')) {
                $table->longText('markdown_report')->nullable()->after('stack_trace');
            }
            if (! Schema::hasColumn('error_reports', 'origin_url')) {
                $table->text('origin_url')->nullable()->after('context');
            }
            if (! Schema::hasColumn('error_reports', 'livewire_component')) {
                $table->string('livewire_component')->nullable()->after('origin_url');
            }
            if (! Schema::hasColumn('error_reports', 'php_version')) {
                $table->string('php_version', 20)->nullable()->after('livewire_component');
            }
            if (! Schema::hasColumn('error_reports', 'laravel_version')) {
                $table->string('laravel_version', 20)->nullable()->after('php_version');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('error_reports', function (Blueprint $table) {
            $table->dropColumn([
                'markdown_report',
                'origin_url',
                'livewire_component',
                'php_version',
                'laravel_version',
            ]);
        });
    }
};
