<?php

namespace Database\Factories;

use App\Models\Gym;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'assigned_to_user_id' => User::factory(),
            'title' => fake()->sentence(),
            'due_date' => fake()->dateTimeBetween('now', '+1 week'),
            'is_completed' => false,
        ];
    }
}
