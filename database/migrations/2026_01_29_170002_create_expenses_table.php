<?php

declare(strict_types=1);

use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpenseStatus;
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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // User who created the expense
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();

            // Core expense details
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('INR');
            $table->date('expense_date')->nullable();
            $table->string('payment_method')->default(ExpensePaymentMethod::CASH->value);
            $table->string('status')->default(ExpenseStatus::DRAFT->value);

            // Vendor information
            $table->string('vendor_name')->nullable();
            $table->string('vendor_contact')->nullable();

            // Reference and attachments
            $table->string('reference_number')->nullable();
            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();

            // Recurring expense settings
            $table->boolean('is_recurring')->default(false)->nullable();
            $table->string('recurring_frequency')->nullable();
            $table->date('next_due_date')->nullable();

            // Workflow timestamps
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index(['gym_id', 'status']);
            $table->index(['gym_id', 'expense_date']);
            $table->index(['gym_id', 'expense_category_id']);
            $table->index(['gym_id', 'user_id']);
            $table->index(['gym_id', 'is_recurring', 'next_due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
