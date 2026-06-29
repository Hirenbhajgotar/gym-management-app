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
        // remove old table
        if (Schema::hasTable('whatsapp_message_packages')) {
            Schema::dropIfExists('whatsapp_message_packages');
        }

        // create new table
        Schema::create('whatsapp_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            $table->integer('message_count');
            $table->decimal('selling_price', 10, 2);
            $table->decimal('twilio_number_cost', 10, 2)->default(0);
            $table->decimal('twilio_msg_cost_per_unit', 10, 4)->default(0.15);

            $table->integer('validity_days')->default(365);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->boolean('includes_number')->default(true);

            $table->json('features')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // remove new table
        if (Schema::hasTable('whatsapp_packages')) {
            Schema::dropIfExists('whatsapp_packages');
        }

        // create old table
        Schema::create('whatsapp_message_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('message_count');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('INR');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }
};
