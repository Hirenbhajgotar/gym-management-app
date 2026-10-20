<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GymMember>
 */
class GymMemberFactory extends Factory
{
    protected $model = GymMember::class;

    public function definition(): array
    {
        $membershipStart = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'gym_id' => Gym::factory(),
            'name' => fake()->name(),
            'profile_photo' => fake()->imageUrl(200, 200, 'people'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->phoneNumber(),
            'age' => rand(18, 60),
            'dob' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'membership_start' => $membershipStart,
            'membership_end' => null, // Will be calculated by the model
            'gym_membership_plan_id' => null, // Will be set in the seeder
            'user_id' => null,
        ];
    }

    /**
     * State: archived member with WhatsApp marketing disabled.
     *
     * $marketingAllowedBeforeArchive is the member's consent immediately before
     * archiving, which archive() copies into whatsapp_marketing_snapshot so
     * unarchive() can put it back. It has to be a parameter rather than read
     * out of the incoming $attributes: those only carry a value when the
     * definition or an earlier chained state put one there, and otherwise
     * yield null — the column's default — not false, so the consent cannot be
     * inferred from what was handed over. The parameter keeps control of the
     * pre-archive consent explicit and deterministic, which is what the
     * snapshot round-trip needs to prove.
     */
    public function archived(bool $marketingAllowedBeforeArchive = false): static
    {
        return $this->state(fn (array $attributes) => [
            'is_archived' => true,
            'archived_at' => now(),
            'allow_whatsapp_marketing' => false,
            'whatsapp_marketing_snapshot' => $marketingAllowedBeforeArchive,
        ]);
    }
}
