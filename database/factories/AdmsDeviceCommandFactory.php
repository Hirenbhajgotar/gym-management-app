<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdmsCommandStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\Gym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmsDeviceCommand>
 */
class AdmsDeviceCommandFactory extends Factory
{
    protected $model = AdmsDeviceCommand::class;

    public function definition(): array
    {
        return [
            'device_id' => BiometricDevice::factory(),
            'gym_id' => function (array $attributes): int|Factory {
                $device = isset($attributes['device_id']) && is_numeric($attributes['device_id'])
                    ? BiometricDevice::query()->find($attributes['device_id'])
                    : null;

                return $device instanceof BiometricDevice ? $device->gym_id : Gym::factory();
            },
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) fake()->numberBetween(1, 999)],
            'status' => AdmsCommandStatusEnum::PENDING,
        ];
    }

    public function userAdd(string $pin, string $name): static
    {
        return $this->state(fn () => [
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => $pin, 'Name' => $name],
        ]);
    }

    public function userDelete(string $pin): static
    {
        return $this->state(fn () => [
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => $pin],
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => AdmsCommandStatusEnum::SENT,
            'sent_at' => now(),
        ]);
    }

    public function acknowledged(): static
    {
        return $this->state(fn () => [
            'status' => AdmsCommandStatusEnum::ACKNOWLEDGED,
            'sent_at' => now()->subMinutes(5),
            'acknowledged_at' => now(),
            'device_return_code' => '0',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => AdmsCommandStatusEnum::FAILED,
            'sent_at' => now()->subMinutes(5),
            'acknowledged_at' => now(),
            'device_return_code' => '-1',
        ]);
    }
}
