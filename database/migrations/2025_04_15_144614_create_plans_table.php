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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index(); // e.g., "Basic", "Pro"
            $table->string('slug')->unique(); // e.g., "basic", "pro"
            $table->decimal('price_monthly', 8, 2)->nullable(); // e.g., 19.99
            $table->decimal('price_yearly', 8, 2)->nullable(); // e.g., 199.99
            $table->string('currency')->default('USD'); // e.g., "USD", "INR"
            $table->boolean('is_active')->default(true); // Plan availability
            $table->unsignedInteger('trial_days')->nullable(); // e.g., 7
            $table->text('description')->nullable(); // Plan description
            $table->text('brief_description')->nullable(); // Plan brief_description
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
