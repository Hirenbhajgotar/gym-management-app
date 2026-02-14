<?php

use App\Enums\WhatsAppTemplateStatusEnum;
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
        // remove old table
        if (Schema::hasTable('whatsapp_templates')) {
            Schema::dropIfExists('whatsapp_templates');
        }

        // create new table
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->onDelete('cascade');

            $table->string('name');
            $table->string('display_name');
            $table->text('body')->nullable();
            $table->string('category')->default('utility')->nullable();

            $table->json('variables')->nullable();
            $table->integer('variable_count')->default(0)->nullable();

            $table->string('twilio_template_sid')->nullable();
            $table->string('meta_template_id')->nullable();

            $table->string('status')->default(WhatsAppTemplateStatusEnum::DRAFT->value)->nullable();
            $table->text('rejection_reason')->nullable();

            $table->boolean('is_default')->default(false)->nullable();
            $table->boolean('is_active')->default(true)->nullable();

            $table->bigInteger('usage_count')->default(0)->nullable();
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            $table->index(['gym_id', 'status']);
            $table->index(['gym_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // remove new table
        if (Schema::hasTable('whatsapp_templates')) {
            Schema::dropIfExists('whatsapp_templates');
        }

        // create old table
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->onDelete('cascade');
            $table->string('template_name'); // e.g., 'birthday_greeting'
            $table->string('template_sid'); // Twilio Content SID (HXxxx...)
            $table->string('language')->default('en')->nullable();
            $table->json('variables')->nullable(); // Template variable definitions
            $table->string('status')->default('pending')->nullable(); // e.g., 'approved', 'pending', 'rejected'
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }
};
