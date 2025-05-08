<?php

namespace Database\Factories;

use App\Models\MemberPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MemberPayment>
 */
class MemberPaymentFactory extends Factory
{
    protected $model = MemberPayment::class;

    public function definition(): array
    {
        return [
            'member_id' => null, // Will be set in the seeder
            'membership_plan_id' => null, // Will be set in the seeder
            'amount' => $this->faker->randomFloat(4, 3000, 9000),
            'currency' => null, // Will be set based on MembershipPlan in the seeder
            'payment_method' => $this->faker->randomElement(['cash', 'card', 'UPI']),
            'status' => $this->faker->randomElement(['Pending', 'Completed', 'Failed']),
            'notes' => $this->faker->optional()->sentence(),
            'payment_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
