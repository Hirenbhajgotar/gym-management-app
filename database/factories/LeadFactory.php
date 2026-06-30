<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Gym;
use App\Models\Lead;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gym_id' => function () {
                // Use an existing gym, or create one if none exist
                $gym = Gym::query()->inRandomOrder()->first();
                if (! $gym) {
                    $gym = Gym::factory()->create();
                }

                return $gym->id;
            },
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'source' => fake()->randomElement(LeadSource::cases()),
            'status' => fake()->randomElement(LeadStatus::cases()),
            'interest' => fake()->optional()->randomElements(['weight_loss', 'muscle_gain', 'general_fitness', 'cardio', 'strength_training', 'yoga', 'group_classes'], rand(1, 3)),
            'tags' => fake()->optional()->randomElements(['hot', 'warm', 'cold', 'vip', 'student', 'corporate', 'senior', 'new'], rand(1, 3)),
            'membership_plan_id' => function (array $attributes) {
                // Use the gym_id from the attributes to create membership plan
                return MembershipPlan::factory()->create(['gym_id' => $attributes['gym_id']])->id;
            },
            'last_follow_up_date' => fake()->optional()->dateTimeBetween('-1 month', 'now'),
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
