<?php

namespace Database\Factories;

use App\Models\Deal;
use App\Models\Gym;
use App\Models\Lead;
use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Deal>
 */
class DealFactory extends Factory
{
    protected $model = Deal::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'lead_id' => Lead::factory(),
            'membership_plan_id' => MembershipPlan::factory(),
            'value' => $this->faker->randomFloat(2, 50, 500),
            'stage' => $this->faker->randomElement(['prospecting', 'proposal', 'negotiation', 'closed_won', 'closed_lost']),
            'expected_close_date' => $this->faker->dateTimeBetween('now', '+1 month'),
        ];
    }
}
