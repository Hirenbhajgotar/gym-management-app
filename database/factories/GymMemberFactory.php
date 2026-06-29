<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GymMember>
 */
class GymMemberFactory extends Factory
{
    protected $model = GymMember::class;

    public function definition(): array
    {
        $membershipStart = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'gym_id' => Gym::factory(),
            'name' => fake()->name(),
            'profile_photo' => fake()->imageUrl(200, 200, 'people'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'age' => rand(18, 60),
            'dob' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'membership_start' => $membershipStart,
            'membership_end' => null, // Will be calculated by the model
            'gym_membership_plan_id' => null, // Will be set in the seeder
            'user_id' => null,
        ];
    }
}
