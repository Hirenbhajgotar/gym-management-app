<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('gym_members', function (Blueprint $table): void {
            $table->boolean('whatsapp_marketing_snapshot')->nullable()->after('allow_whatsapp_marketing');
        });
    }

    public function down(): void
    {
        Schema::table('gym_members', function (Blueprint $table): void {
            $table->dropColumn('whatsapp_marketing_snapshot');
        });
    }
};
