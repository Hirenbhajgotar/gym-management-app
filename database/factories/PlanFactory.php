<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'slug' => Str::slug(fake()->sentence(2)) . '-' . Str::random(6),
            'description' => fake()->sentence(),
            'brief_description' => fake()->sentence(),
            'price_monthly' => fake()->numberBetween(500, 2000),
            'price_yearly' => fake()->numberBetween(5000, 20000),
            'currency' => 'INR',
            'is_active' => true,
            'trial_days' => 0,
        ];
    }
}
