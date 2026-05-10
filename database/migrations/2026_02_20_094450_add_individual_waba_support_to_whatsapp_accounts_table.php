<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->string('waba_name')->nullable()->after('waba_id');
            $table->string('waba_status')->nullable()->default('pending')->after('waba_name');
            $table->string('waba_mode')->nullable()->default('shared')->after('waba_status');

            $table->string('meta_business_portfolio_id')->nullable()->after('messaging_service_sid');
            $table->text('meta_access_token')->nullable()->after('meta_business_portfolio_id');

            $table->boolean('embedded_signup_completed')->nullable()->default(false)->after('meta_access_token');
            $table->string('embedded_signup_session_id')->nullable()->after('embedded_signup_completed');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'waba_name',
                'waba_status',
                'waba_mode',
                'meta_business_portfolio_id',
                'meta_access_token',
                'embedded_signup_completed',
                'embedded_signup_session_id',
            ]);
        });
    }
};
