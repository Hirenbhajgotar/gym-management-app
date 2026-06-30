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
        Schema::create('expense_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained('expense_categories')->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');

            // Budget period (Calendar Year based)
            $table->enum('period', ['monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->date('start_date');
            $table->date('end_date');

            // Alert settings
            $table->unsignedTinyInteger('alert_threshold')->default(80); // Percentage
            $table->boolean('alert_sent')->default(false);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index(['gym_id', 'is_active']);
            $table->index(['gym_id', 'expense_category_id']);
            $table->index(['gym_id', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_budgets');
    }
};
