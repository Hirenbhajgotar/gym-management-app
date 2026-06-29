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
        Schema::create('error_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('error_type')->nullable()->default('user_reported');
            $table->text('error_message')->nullable();
            $table->longText('stack_trace')->nullable();
            $table->json('context')->nullable(); // URL, request data, etc.
            $table->string('status')->default('pending')->nullable(); // pending, investigating, resolved
            $table->text('user_description')->nullable();
            $table->string('priority')->default('medium')->nullable(); // low, medium, high, critical
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index('occurred_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('error_reports');
    }
};
