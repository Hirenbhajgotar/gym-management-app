<?php

namespace Database\Factories;

use App\Models\PermissionSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class PermissionSettingFactory extends Factory
{
    protected $model = PermissionSetting::class;

    public function definition(): array
    {
        return [
            'role' => $this->faker->word(),
            'default_resources' => $this->faker->word(),
            'default_permissions' => $this->faker->words(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
