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
            $table->longText('markdown_report')->nullable()->after('stack_trace');
            $table->text('origin_url')->nullable()->after('context');
            $table->string('livewire_component')->nullable()->after('origin_url');
            $table->string('php_version', 20)->nullable()->after('livewire_component');
            $table->string('laravel_version', 20)->nullable()->after('php_version');
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
