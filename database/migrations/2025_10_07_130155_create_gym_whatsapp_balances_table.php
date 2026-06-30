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
        Schema::create('gym_whatsapp_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->onDelete('cascade');
            $table->integer('balance_messages')->default(0);
            $table->integer('total_messages_purchased')->default(0);
            $table->integer('messages_used')->default(0);
            $table->boolean('low_balance_alert_sent')->default(false);
            $table->timestamps();

            $table->unique('gym_id');
            $table->index(['gym_id', 'balance_messages']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_whatsapp_balances');
    }
};
