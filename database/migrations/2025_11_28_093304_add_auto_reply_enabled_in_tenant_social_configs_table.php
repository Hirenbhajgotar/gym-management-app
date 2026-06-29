<?php

declare(strict_types=1);

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
        Schema::table('tenant_social_configs', function (Blueprint $table) {
            $table->boolean('auto_reply_enabled')->default(false)->nullable()->after('is_active');
            $table->boolean('sms_acknowledgment_enabled')->default(false)->nullable()->after('auto_reply_enabled');
            $table->boolean('email_acknowledgment_enabled')->default(false)->nullable()->after('sms_acknowledgment_enabled');
            $table->text('sms_template')->nullable()->after('email_acknowledgment_enabled');
            $table->text('email_template')->nullable()->after('sms_template');
            $table->integer('reply_delay_minutes')->default(0)->nullable()->after('email_template');
            $table->boolean('debug_mode')->default(false)->nullable()->after('reply_delay_minutes');
            $table->integer('rate_limit')->default(0)->nullable()->after('debug_mode');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_social_configs', function (Blueprint $table) {
            $table->dropColumn([
                'auto_reply_enabled',
                'sms_acknowledgment_enabled',
                'email_acknowledgment_enabled',
                'sms_template',
                'email_template',
                'reply_delay_minutes',
                'debug_mode',
                'rate_limit',
            ]);
        });
    }
};
