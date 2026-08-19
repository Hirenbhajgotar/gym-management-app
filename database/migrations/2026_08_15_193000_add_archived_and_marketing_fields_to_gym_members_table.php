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
        Schema::table('gym_members', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false)->index()->after('is_frozen');
            $table->timestamp('archived_at')->nullable()->after('is_archived');
            $table->boolean('allow_whatsapp_marketing')->default(true)->index()->after('archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gym_members', function (Blueprint $table) {
            $table->dropColumn(['is_archived', 'archived_at', 'allow_whatsapp_marketing']);
        });
    }
};
