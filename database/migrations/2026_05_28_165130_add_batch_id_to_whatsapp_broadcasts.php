<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('whatsapp_broadcasts', function (Blueprint $table) {
            $table->string('batch_id', 36)
                ->nullable()
                ->after('status')
                ->comment('Laravel job batch ID for Bus::batch tracking');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_broadcasts', function (Blueprint $table) {
            $table->dropColumn('batch_id');
        });
    }
};
