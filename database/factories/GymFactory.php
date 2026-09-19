<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Gym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gym>
 */
class GymFactory extends Factory
{
    protected $model = Gym::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->slug(),
        ];
    }

    public function withCheckInQrCodeToken(?string $token = null): static
    {
        return $this->afterCreating(function (Gym $gym) use ($token) {
            $gym->forceFill([
                'qr_code_token' => $token ?? fake()->regexify('[A-Za-z0-9]{32}'),
            ])->saveQuietly();
        });
    }

    public function withCheckoutQrCodeToken(?string $token = null): static
    {
        return $this->afterCreating(function (Gym $gym) use ($token) {
            $gym->forceFill([
                'qr_code_checkout_token' => $token ?? fake()->regexify('[A-Za-z0-9]{32}'),
            ])->saveQuietly();
        });
    }
}
