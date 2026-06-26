<?php

namespace Database\Factories;

use Illuminate\Support\Facades\Date;
use App\Models\PermissionSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermissionSetting>
 */
class PermissionSettingFactory extends Factory
{
    protected $model = PermissionSetting::class;

    public function definition(): array
    {
        return [
            'role' => $this->faker->word(),
            'default_resources' => $this->faker->word(),
            'default_permissions' => $this->faker->words(),
            'created_at' => Date::now(),
            'updated_at' => Date::now(),
        ];
    }
}
