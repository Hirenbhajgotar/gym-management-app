<?php

namespace Database\Factories;

use App\Models\Gym;
use App\Models\Interaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Interaction>
 */
class InteractionFactory extends Factory
{
    protected $model = Interaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(['call', 'email', 'tour', 'meeting']),
            'notes' => $this->faker->paragraph,
            'interaction_date' => $this->faker->dateTimeThisMonth(),
        ];
    }
}
