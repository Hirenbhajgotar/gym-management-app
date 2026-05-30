<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a BTREE index on the `status` column of `whatsapp_broadcasts` to
     * support efficient filtering during broadcast orchestration queries
     * (e.g. WHERE status IN ('sending', 'processing')).
     */
    public function up(): void
    {
        Schema::table('whatsapp_broadcasts', function (Blueprint $table): void {
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_broadcasts', function (Blueprint $table): void {
            $table->dropIndex(['status']);
        });
    }
};
