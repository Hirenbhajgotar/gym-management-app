<?php

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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Gym owner
            $table->foreignId('plan_id')->constrained()->onDelete('cascade'); // SaaS plan
            $table->string('status')->default('trial'); // trial, active, cancelled, expired, suspended
            $table->string('billing_cycle')->default('monthly'); // monthly, yearly
            $table->decimal('price', 8, 2); // Current price at time of subscription
            $table->string('currency')->default('INR');
            $table->timestamp('trial_ends_at')->nullable(); // When trial period ends
            $table->timestamp('current_period_start')->nullable(); // Start of current billing period
            $table->timestamp('current_period_end')->nullable(); // End of current billing period
            $table->timestamp('ends_at')->nullable(); // When subscription ends (for cancelled subscriptions)
            $table->timestamp('cancelled_at')->nullable(); // When subscription was cancelled
            $table->json('metadata')->nullable(); // Additional data like payment method, etc.
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['plan_id', 'status']);
            $table->index('current_period_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
