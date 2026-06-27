<?php

namespace Database\Factories;

use App\Models\MemberPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberPayment>
 */
class MemberPaymentFactory extends Factory
{
    protected $model = MemberPayment::class;

    public function definition(): array
    {
        return [
            'member_id' => null, // Will be set in the seeder
            'membership_plan_id' => null, // Will be set in the seeder
            'amount' => fake()->randomFloat(4, 3000, 9000),
            'currency' => null, // Will be set based on MembershipPlan in the seeder
            'payment_method' => fake()->randomElement(['cash', 'card', 'UPI']),
            'status' => fake()->randomElement(['Pending', 'Completed', 'Failed']),
            'notes' => fake()->optional()->sentence(),
            'payment_date' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
