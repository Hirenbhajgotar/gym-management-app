<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('currency')->default('INR');
            $table->decimal('price_monthly', 10, 2)->nullable();
            $table->decimal('price_yearly', 10, 2)->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'effective_from']);
        });

        // Seed an initial price row for every existing plan so PriceResolver
        // has history to read immediately after the migration.
        $plans = DB::table('plans')->get();
        $now = now();

        foreach ($plans as $plan) {
            DB::table('plan_prices')->insert([
                'plan_id' => $plan->id,
                'currency' => $plan->currency ?? 'INR',
                'price_monthly' => $plan->price_monthly,
                'price_yearly' => $plan->price_yearly,
                'effective_from' => $plan->created_at ?? $now,
                'effective_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};
