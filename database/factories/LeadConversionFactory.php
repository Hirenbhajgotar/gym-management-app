<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Lead;
use App\Models\LeadConversion;
use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadConversion>
 */
class LeadConversionFactory extends Factory
{
    protected $model = LeadConversion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'gym_member_id' => GymMember::factory(),
            'membership_plan_id' => function () {
                // Get a gym first, then create a membership plan for that gym
                $gym = Gym::query()->inRandomOrder()->first() ?? Gym::factory()->create();

                return MembershipPlan::factory()->create(['gym_id' => $gym->id])->id;
            },
            'conversion_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
