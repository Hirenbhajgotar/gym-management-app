<?php

namespace Database\Factories;

use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MembershipPlan>
 */
class MembershipPlanFactory extends Factory
{
    protected $model = MembershipPlan::class;

    public function definition(): array
    {
        return [
            'gym_id' => null, // Will be set in the seeder
            'name' => $this->faker->word() . ' Plan',
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(4, 3000, 10000),
            'duration' => $this->faker->randomElement([3, 6, 12, 15]), // Duration in months
            'currency' => $this->faker->randomElement(['INR', 'USD']),
            'is_active' => true,
        ];
    }
}
