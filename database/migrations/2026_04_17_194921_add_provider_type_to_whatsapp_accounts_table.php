<?php

declare(strict_types=1);

use App\Enums\WhatsAppProviderTypeEnum;
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
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->string('provider_type', 20)
                ->default(WhatsAppProviderTypeEnum::TWILIO->value)
                ->nullable()
                ->after('business_description');

            // Index for provider_type queries (per-tenant provider selection)
            $table->index('provider_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropIndex(['provider_type']);
            $table->dropColumn(['provider_type']);
        });
    }
};
