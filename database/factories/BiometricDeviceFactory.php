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

    /**
     * Hash and salt are deliberately not mass-assignable; factories still need
     * to seed verifier state for tests, so apply attributes explicitly.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): BiometricDevice
    {
        /** @var BiometricDevice $model */
        $model = parent::newModel([]);

        $model->fill($attributes);

        // Only the non-mass-assignable verifier fields need the explicit path;
        // force-filling every attribute would weaken the mass-assignment guard
        // for the rest of the model.
        return $model->forceFill(array_intersect_key($attributes, array_flip([
            'adms_comm_key_hash',
            'adms_comm_key_salt',
        ])));
    }

    /**
     * Install a salted verifier without going through `$fillable`.
     */
    public function withCommKey(string $key, ?string $salt = null): static
    {
        return $this->state(function () use ($key, $salt): array {
            $salt ??= BiometricDevice::generateCommKeySalt();

            return [
                'adms_comm_key_salt' => $salt,
                'adms_comm_key_hash' => BiometricDevice::hashCommKey($key, $salt),
            ];
        });
    }

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

    public function withWebhookSecret(string $secret): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider_config' => array_merge(
                is_array($attributes['provider_config'] ?? null) ? $attributes['provider_config'] : [],
                ['webhook_secret' => $secret],
            ),
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

    /**
     * An ADMS-enabled terminal that also carries a provider-backed config.
     *
     * ADR-004 blesses dual-mode devices: the terminal is driven through
     * `user_add`/`user_delete` while a cloud provider holds the credential for
     * the same PIN. That is exactly the shape where the erasure dispatch has to
     * keep the two paths apart, and {@see self::admsEnabled()} cannot express it
     * because it nulls `provider_type`.
     */
    public function dualMode(string $serial = 'TEST-SN-DUAL'): static
    {
        return $this->state(fn (array $attributes): array => [
            'device_serial' => $serial,
            'adms_enabled' => true,
            'provider_type' => BiometricProvidersEnum::Rekognition,
        ]);
    }
}
