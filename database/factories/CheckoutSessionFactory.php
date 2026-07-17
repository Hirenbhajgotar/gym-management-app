<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CheckoutSession>
 */
class CheckoutSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => Str::random(40),
            'user_id' => User::factory(),
            'type' => fake()->randomElement([
                CheckoutSession::TYPE_NEW,
                CheckoutSession::TYPE_UPGRADE,
                CheckoutSession::TYPE_RENEWAL,
            ]),
            'plan_id' => Plan::factory(),
            'subscription_id' => null,
            'billing_cycle' => 'yearly',
            'amount' => fake()->randomFloat(2, 1000, 20000),
            'currency' => Currency::INR,
            'status' => CheckoutSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
            'metadata' => [],
        ];
    }
}
