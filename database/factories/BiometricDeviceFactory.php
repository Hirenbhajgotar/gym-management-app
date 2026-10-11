<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricProvidersEnum;
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
            'device_type' => BiometricTypeEnum::AllInOne->value,
            'provider_type' => fake()->randomElement([
                BiometricProvidersEnum::Rekognition,
                BiometricProvidersEnum::AzureFace,
                BiometricProvidersEnum::BioID,
                BiometricProvidersEnum::OnPremise,
                BiometricProvidersEnum::Custom,
            ]),
            'provider_config' => ['threshold' => 0.8],
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'device_token' => Hash::make($plainToken),
            'token_hash' => hash('sha256', $plainToken),
            'created_by' => User::factory(),
        ];
    }

    public function administrativelyDisabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }

    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes) => [
            'device_token' => Hash::make($token),
            'token_hash' => hash('sha256', $token),
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

    public function deviceNative(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_type' => null,
            'provider_config' => null,
        ]);
    }

    public function admsEnabled(string $serial = 'TEST-SN-001'): static
    {
        return $this->state(fn (array $attributes) => [
            'device_serial' => $serial,
            'adms_enabled' => true,
            'provider_type' => null,
            'provider_config' => null,
        ]);
    }
}
