<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BiometricConsent;
use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BiometricConsent>
 */
class BiometricConsentFactory extends Factory
{
    protected $model = BiometricConsent::class;

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
            'ip_address' => fake()->ipv4(),
            'version' => '1.0',
            'consented_at' => now(),
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
        ]);
    }
}
