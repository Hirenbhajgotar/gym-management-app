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
        Schema::create('whatsapp_message_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->onDelete('cascade');

            $table->integer('total_credits')->default(0)->nullable();
            $table->integer('used_credits')->default(0)->nullable();
            $table->integer('remaining_credits')->default(0)->nullable();

            $table->decimal('cost_per_message', 10, 4)->default(0.15)->nullable();
            $table->decimal('selling_price_per_msg', 10, 4)->default(0.25)->nullable();

            $table->integer('welcome_credits_used')->default(0)->nullable();
            $table->boolean('welcome_credits_claimed')->default(false)->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_credits');
    }
};
