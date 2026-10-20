<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BiometricTemplateSyncStatusEnum;
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
            // Deterministic, not random: the cloud/hardware fork in `hasCloudProvider()` must
            // not vary between runs. A test that needs the other side uses `deviceNative()` or
            // `cloudBacked()` explicitly rather than relying on a coin flip.
            'provider_type' => 'custom',
            'template_ref' => json_encode([
                'provider' => 'custom',
                'external_id' => fake()->uuid(),
            ]),
            'quality_score' => fake()->randomFloat(2, 0.7, 1.0),
            'is_active' => true,
            'enrolled_at' => now(),
            // The default row must be internally consistent: a `Synced` template whose
            // hardware confirmation was never recorded is the shape the backfill migration
            // exists to repair, so producing it by default lets tests silently exercise the
            // legacy fallback path. Use `legacy()` for that shape on purpose.
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'hardware_confirmed_at' => now(),
        ];
    }

    /**
     * Reproduce the pre-backfill cohort: `Synced` with no hardware confirmation recorded.
     */
    public function legacy(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'hardware_confirmed_at' => null,
        ]);
    }

    /**
     * A credential the ADMS terminal holds itself.
     */
    public function deviceNative(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider_type' => 'device_native',
        ]);
    }

    /**
     * A credential a cloud provider holds.
     */
    public function cloudBacked(string $provider = 'custom'): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider_type' => $provider,
        ]);
    }

    /**
     * A machine-written erasure obligation (an orphan marker), not an enrolment record.
     */
    public function erasureObligation(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_erasure_obligation' => true,
            'is_active' => false,
            'enrolled_at' => null,
            'hardware_confirmed_at' => null,
        ]);
    }

    /**
     * A row parked at `PendingDeletion` because the member made an Art. 17 erasure request.
     *
     * Distinct from a machine park (archive/sweep/reconciler), which carries no request marker.
     */
    public function memberErasureRequest(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
            'erasure_requested_at' => now(),
        ]);
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

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
            'provider_type' => 'device_native',
            'template_ref' => null,
            'is_active' => false,
            'enrolled_at' => null,
            'hardware_confirmed_at' => null,
        ]);
    }

    public function synced(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
            'enrolled_at' => now(),
            'hardware_confirmed_at' => now(),
        ]);
    }

    public function propagated(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => BiometricTemplateSyncStatusEnum::Propagated,
            'is_active' => true,
            'enrolled_at' => now(),
            'hardware_confirmed_at' => null,
        ]);
    }
}
