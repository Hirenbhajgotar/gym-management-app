<?php

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
                $gym = Gym::inRandomOrder()->first();
                if (! $gym) {
                    $gym = Gym::factory()->create();
                }

                return $gym->id;
            },
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'source' => $this->faker->randomElement(LeadSource::cases()),
            'status' => $this->faker->randomElement(LeadStatus::cases()),
            'interest' => $this->faker->optional()->randomElements(['weight_loss', 'muscle_gain', 'general_fitness', 'cardio', 'strength_training', 'yoga', 'group_classes'], rand(1, 3)),
            'tags' => $this->faker->optional()->randomElements(['hot', 'warm', 'cold', 'vip', 'student', 'corporate', 'senior', 'new'], rand(1, 3)),
            'membership_plan_id' => function (array $attributes) {
                // Use the gym_id from the attributes to create membership plan
                return MembershipPlan::factory()->create(['gym_id' => $attributes['gym_id']])->id;
            },
            'last_follow_up_date' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'notes' => $this->faker->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
