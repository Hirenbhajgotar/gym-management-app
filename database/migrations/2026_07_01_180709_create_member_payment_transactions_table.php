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
        if (! Schema::hasTable('member_payment_transactions')) {
            Schema::create('member_payment_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('member_payment_id')->constrained('member_payments')->cascadeOnDelete();
                $table->decimal('amount', 8, 2)->nullable();
                $table->string('currency')->default('INR')->nullable();
                $table->string('payment_method')->default('cash')->nullable();
                $table->date('transaction_date')->nullable();
                $table->string('status')->default('completed')->nullable(); // completed, pending, failed, refunded
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('member_payment_id');
                $table->index('status');
                $table->index('transaction_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_payment_transactions');
    }
};
