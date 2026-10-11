<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BiometricReceiptStatusEnum;
use App\Models\BiometricDevice;
use App\Models\BiometricEventReceipt;
use App\Models\Gym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BiometricEventReceipt>
 */
class BiometricEventReceiptFactory extends Factory
{
    protected $model = BiometricEventReceipt::class;

    /**
     * @return array<string, mixed>
     */
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
            'event_fingerprint' => fake()->sha256(),
            'status' => BiometricReceiptStatusEnum::CLAIMED,
            'attendance_id' => null,
            'payload_summary' => [],
        ];
    }
}
