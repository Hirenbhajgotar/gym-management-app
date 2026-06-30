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
        Schema::create('social_media_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_social_config_id')
                ->constrained('tenant_social_configs')
                ->onDelete('cascade');
            $table->string('webhook_id')->nullable();
            $table->string('platform', 50);
            $table->json('payload');
            $table->json('response')->nullable();
            $table->enum('status', ['received', 'processing', 'success', 'failed', 'error'])
                ->default('received');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_social_config_id', 'status']);
            $table->index(['platform', 'status']);
            $table->index('webhook_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_media_webhook_logs');
    }
};
