<?php

namespace Database\Factories;

use App\Models\GymMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GymMember>
 */
class GymMemberFactory extends Factory
{
    protected $model = GymMember::class;

    public function definition(): array
    {
        $membershipStart = $this->faker->dateTimeBetween('-1 year', 'now');

        return [
            'gym_id' => \App\Models\Gym::factory(),
            'name' => $this->faker->name(),
            'profile_photo' => $this->faker->imageUrl(200, 200, 'people'),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'age' => rand(18, 60),
            'dob' => $this->faker->dateTimeBetween('-60 years', '-18 years'),
            'membership_start' => $membershipStart,
            'membership_end' => null, // Will be calculated by the model
            'gym_membership_plan_id' => null, // Will be set in the seeder
            'user_id' => null,
        ];
    }
}
