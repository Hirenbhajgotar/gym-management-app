<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanPrice>
 */
class PlanPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'currency' => Currency::INR,
            'price_monthly' => fake()->randomFloat(2, 100, 2000),
            'price_yearly' => fake()->randomFloat(2, 1000, 20000),
            'effective_from' => now()->subDay(),
            'effective_to' => null,
        ];
    }
}
