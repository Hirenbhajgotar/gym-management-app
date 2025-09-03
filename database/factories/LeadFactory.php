<?php

namespace Database\Factories;

use App\Models\Gym;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lead>
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
            'gym_id' => Gym::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'source' => $this->faker->randomElement(['website', 'walk_in', 'referral', 'social_media', 'other']),
            'status' => $this->faker->randomElement(['new', 'contacted', 'qualified', 'unqualified']),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
