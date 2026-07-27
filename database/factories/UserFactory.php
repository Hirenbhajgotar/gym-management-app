<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => UserStatus::ACTIVE,
            'is_active' => true, // Explicitly keeping it true as well, though observer would sync it.
        ];
    }

    public function gymOwner(): self
    {
        return $this->state(function (array $attributes) {
            return [];
        })->afterCreating(function (User $user) {
            $user->assignRole('gym_owner');
        });
    }
}
