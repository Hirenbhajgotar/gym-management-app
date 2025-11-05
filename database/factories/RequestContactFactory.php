<?php

namespace Database\Factories;

use App\Models\RequestContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RequestContact>
 */
class RequestContactFactory extends Factory
{
    protected $model = RequestContact::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            "name" => fake()->name(),
            "email" => fake()->unique()->safeEmail(),
            "phone" => fake()->phoneNumber(),
            "company_name" => fake()->boolean(70) ? fake()->company() : null,
            "gym_size" => fake()->boolean(80)
                ? fake()->numberBetween(50, 5000)
                : null,
            "message" => fake()->boolean(60) ? fake()->paragraph(3) : null,
            "ip_address" => fake()->ipv4(),
            "user_agent" => fake()->userAgent(),
            "is_contacted" => fake()->boolean(30),
            "contacted_at" => fake()->boolean(30)
                ? fake()->dateTimeBetween("-30 days", "now")
                : null,
            "admin_notes" => fake()->boolean(20) ? fake()->sentence() : null,
            "created_at" => fake()->dateTimeBetween("-60 days", "now"),
        ];
    }

    /**
     * Indicate that the request has been contacted.
     */
    public function contacted(): static
    {
        return $this->state(
            fn(array $attributes) => [
                "is_contacted" => true,
                "contacted_at" => fake()->dateTimeBetween("-30 days", "now"),
                "admin_notes" => fake()->sentence(),
            ],
        );
    }

    /**
     * Indicate that the request has not been contacted.
     */
    public function uncontacted(): static
    {
        return $this->state(
            fn(array $attributes) => [
                "is_contacted" => false,
                "contacted_at" => null,
                "admin_notes" => null,
            ],
        );
    }

    /**
     * Indicate that the request is recent (last 7 days).
     */
    public function recent(): static
    {
        return $this->state(
            fn(array $attributes) => [
                "created_at" => fake()->dateTimeBetween("-7 days", "now"),
                "is_contacted" => false,
                "contacted_at" => null,
            ],
        );
    }
}
