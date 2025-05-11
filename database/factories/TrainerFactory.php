<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Gym;
use App\Models\Trainer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Trainer>
 */
class TrainerFactory extends Factory
{
    protected $model = Trainer::class;

    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'specialization' => $this->faker->randomElement(['Strength Training', 'Yoga', 'Cardio', 'CrossFit']),
            'experience_years' => $this->faker->numberBetween(1, 20),
            'certifications' => $this->faker->randomElement(['Certified Personal Trainer (CPT)', 'Yoga Instructor', 'CrossFit Level 1']),
            'is_available' => $this->faker->boolean(80),
            'available_hours' => $this->faker->randomElement(['Mon-Fri: 6 AM - 12 PM, 4 PM - 8 PM', 'Mon-Sat: 8 AM - 2 PM', 'Tue-Sun: 2 PM - 8 PM']),
            // 'status' => $this->faker->boolean(90),
            'basic_salary' => $this->faker->randomFloat(4, 10000, 40000),
            'pt_salary_per_member' => $this->faker->randomFloat(4, 2000, 10000),
            // 'currency' => $this->faker->randomElement(['USD', 'INR', 'EUR']),
            $this->faker->randomElement(array_column(Currency::cases(), 'value')),
        ];
    }
}
