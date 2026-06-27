<?php

namespace Database\Factories;

use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<BiometricDevice>
 */
class BiometricDeviceFactory extends Factory
{
    protected $model = BiometricDevice::class;

    public function definition(): array
    {
        $plainToken = bin2hex(random_bytes(32));

        return [
            'gym_id' => Gym::factory(),
            'device_name' => fake()->word() . ' Device',
            'device_type' => BiometricTypeEnum::Face->value,
            'provider_type' => fake()->randomElement(['rekognition', 'azure_face', 'bioid', 'on_premise', 'custom']),
            'provider_config' => json_encode(['threshold' => 0.8]),
            'status' => BiometricDeviceStatusEnum::Active,
            'device_token' => Hash::make($plainToken),
            'offline_capable' => true,
            'supports_multi_modal' => false,
            'created_by' => User::factory(),
        ];
    }

    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes) => [
            'device_token' => Hash::make($token),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BiometricDeviceStatusEnum::Inactive,
        ]);
    }

    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BiometricDeviceStatusEnum::Maintenance,
        ]);
    }
}
