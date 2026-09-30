<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BiometricTypeEnum;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BiometricTemplate>
 */
class BiometricTemplateFactory extends Factory
{
    protected $model = BiometricTemplate::class;

    public function definition(): array
    {
        return [
            'gym_member_id' => GymMember::factory(),
            'gym_id' => function (array $attributes): int|Factory {
                $member = isset($attributes['gym_member_id']) && is_numeric($attributes['gym_member_id'])
                    ? GymMember::query()->find($attributes['gym_member_id'])
                    : null;

                return $member instanceof GymMember ? $member->gym_id : Gym::factory();
            },
            'enrolled_by_device_id' => null,
            'biometric_type' => BiometricTypeEnum::Face,
            'provider_type' => fake()->randomElement(['rekognition', 'azure', 'bioid', 'local', 'custom']),
            'template_ref' => json_encode([
                'provider' => 'custom',
                'external_id' => fake()->uuid(),
            ]),
            'quality_score' => fake()->randomFloat(2, 0.7, 1.0),
            'is_active' => true,
            'enrolled_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function face(): static
    {
        return $this->state(fn (array $attributes) => [
            'biometric_type' => BiometricTypeEnum::Face,
        ]);
    }

    public function fingerprint(): static
    {
        return $this->state(fn (array $attributes) => [
            'biometric_type' => BiometricTypeEnum::Fingerprint,
        ]);
    }
}
