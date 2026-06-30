<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Spatie\Permission\Models\Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(), // e.g., 'gym_owner', 'trainer'
            'gym_id' => null, // Default to null; override in tests as needed
            'guard_name' => 'web', // Required by Spatie’s Laravel Permission
        ];
    }
}
