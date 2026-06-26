<?php

namespace Database\Factories;

use App\Enums\FollowUpStatus;
use App\Enums\InteractionType;
use App\Models\FollowUp;
use App\Models\Gym;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FollowUp>
 */
class FollowUpFactory extends Factory
{
    protected $model = FollowUp::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'gym_id' => Gym::factory(),
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(InteractionType::cases()),
            'scheduled_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'status' => $this->faker->randomElement(FollowUpStatus::cases()),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
