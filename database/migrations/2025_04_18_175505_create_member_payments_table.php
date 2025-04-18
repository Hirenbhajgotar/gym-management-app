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
        Schema::create('member_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained('gym_members')->onDelete('cascade');
            $table->foreignId('membership_plan_id')->nullable()->constrained('membership_plans');
            $table->decimal('amount', 8, 2)->nullable();
            $table->string('currency')->default('INR')->nullable();
            $table->string('payment_method')->default('cash')->nullable(); // cash, upi
            $table->string('status')->default('completed')->nullable(); // completed, pending
            $table->text('notes')->nullable();
            $table->date('payment_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_payments');
    }
};
