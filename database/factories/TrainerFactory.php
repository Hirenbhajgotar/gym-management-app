<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Gym;
use App\Models\Trainer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trainer>
 */
class TrainerFactory extends Factory
{
    protected $model = Trainer::class;

    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'specialization' => fake()->randomElement(['Strength Training', 'Yoga', 'Cardio', 'CrossFit']),
            'experience_years' => fake()->numberBetween(1, 20),
            'certifications' => fake()->randomElement(['Certified Personal Trainer (CPT)', 'Yoga Instructor', 'CrossFit Level 1']),
            'is_available' => fake()->boolean(80),
            'available_hours' => fake()->randomElement(['Mon-Fri: 6 AM - 12 PM, 4 PM - 8 PM', 'Mon-Sat: 8 AM - 2 PM', 'Tue-Sun: 2 PM - 8 PM']),
            // 'status' => $this->faker->boolean(90),
            'basic_salary' => fake()->randomFloat(4, 10000, 40000),
            'pt_salary_per_member' => fake()->randomFloat(4, 2000, 10000),
            // 'currency' => $this->faker->randomElement(['USD', 'INR', 'EUR']),
            fake()->randomElement(array_column(Currency::cases(), 'value')),
        ];
    }
}
