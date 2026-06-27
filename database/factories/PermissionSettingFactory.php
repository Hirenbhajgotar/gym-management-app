<?php

namespace Database\Factories;

use App\Models\PermissionSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<PermissionSetting>
 */
class PermissionSettingFactory extends Factory
{
    protected $model = PermissionSetting::class;

    public function definition(): array
    {
        return [
            'role' => fake()->word(),
            'default_resources' => fake()->word(),
            'default_permissions' => fake()->words(),
            'created_at' => Date::now(),
            'updated_at' => Date::now(),
        ];
    }
}
