<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The archive policy and two check-in paths compare `gym_id` with strict `===`
 * (`AdmsEventService`, `CheckInService`, `BiometricDeProvisioner`). `GymMember`
 * casts `gym_id` to int, so if the sibling models did not, a driver that
 * stringified the column would deny every ADMS/cloud check-in with
 * `member_not_found` — an availability regression that fails closed.
 *
 * A runtime "is it an int" assertion cannot see this on the MySQL test driver,
 * which already returns ints for the column. The cast declaration is asserted
 * directly instead, so deleting it turns the test red.
 */
class BiometricGymScopeCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_models_meeting_at_a_strict_gym_id_comparison_declare_the_integer_cast(): void
    {
        $models = [new GymMember(), new BiometricDevice(), new BiometricTemplate()];

        foreach ($models as $model) {
            $this->assertSame(
                'integer',
                $model->getCasts()['gym_id'] ?? null,
                $model::class . ' must cast gym_id to int so the strict gym-scope comparisons hold.'
            );
        }
    }

    public function test_the_cast_makes_a_strict_comparison_match_a_gym_key(): void
    {
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create(['gym_id' => $gym->id]);

        $hydrated = GymMember::query()->findOrFail($member->id);

        $this->assertSame($gym->id, $hydrated->gym_id);
        $this->assertTrue($hydrated->gym_id === $gym->id);
    }
}
