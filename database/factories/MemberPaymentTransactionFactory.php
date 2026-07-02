<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\MemberPayment;
use App\Models\MemberPaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberPaymentTransaction>
 */
class MemberPaymentTransactionFactory extends Factory
{
    protected $model = MemberPaymentTransaction::class;

    public function definition(): array
    {
        return [
            'member_payment_id' => MemberPayment::factory(),
            'amount' => fake()->randomFloat(2, 500, 5000),
            'currency' => Currency::INR,
            'payment_method' => fake()->randomElement(['cash', 'card', 'UPI']),
            'transaction_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'status' => 'completed',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
