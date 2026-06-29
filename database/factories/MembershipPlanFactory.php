<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipPlan>
 */
class MembershipPlanFactory extends Factory
{
    protected $model = MembershipPlan::class;

    public function definition(): array
    {
        return [
            'gym_id' => null, // Will be set in the seeder
            'name' => fake()->word() . ' Plan',
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(4, 3000, 10000),
            'duration' => fake()->randomElement([3, 6, 12, 15]), // Duration in months
            'currency' => fake()->randomElement(['INR', 'USD']),
            'is_active' => true,
        ];
    }
}
