<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BiometricTypeEnum;
use App\Http\Requests\BiometricEnrollRequest;
use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Round-trip coverage for BiometricEnrollRequest::validated().
 *
 * The endpoint reads validated('biometric_type') as a BiometricTypeEnum and
 * calls ->value on it. FormRequest::validated() returns the validator's raw
 * data, so without the override a real request died with an ErrorException the
 * controller's catch (Exception) cannot see — a 500 instead of the 422 it means
 * to return. These cases drive the override directly for both call shapes.
 */
class BiometricEnrollRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validated_coerces_the_biometric_type_for_both_call_shapes(): void
    {
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create(['gym_id' => $gym->id]);

        $request = BiometricEnrollRequest::create('/api/v1/biometric/enroll', 'POST', [
            'gym_member_id' => $member->id,
            'biometric_type' => BiometricTypeEnum::Face->value,
            'data' => 'sample-data',
        ]);

        $validator = Validator::make($request->all(), $request->rules());
        $this->assertTrue($validator->passes(), 'Precondition: the payload must validate.');

        $request->setValidator($validator);

        // Single-key shape, which is what the controller uses.
        $this->assertInstanceOf(BiometricTypeEnum::class, $request->validated('biometric_type'));
        $this->assertSame(BiometricTypeEnum::Face, $request->validated('biometric_type'));

        // Whole-array shape.
        $all = $request->validated();
        $this->assertIsArray($all);
        $this->assertInstanceOf(BiometricTypeEnum::class, $all['biometric_type']);
        $this->assertSame(BiometricTypeEnum::Face, $all['biometric_type']);
    }

    public function test_validated_coerces_the_biometric_type_when_the_key_is_an_array(): void
    {
        // An array key is a data_get() PATH, so validated(['biometric_type'])
        // resolves to the raw scalar at that path (not a sub-array). Without
        // coercion the caller gets a plain string and the controller's ->value
        // read dies — the same 500 the override exists to prevent.
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create(['gym_id' => $gym->id]);

        $request = BiometricEnrollRequest::create('/api/v1/biometric/enroll', 'POST', [
            'gym_member_id' => $member->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
            'data' => 'sample-data',
        ]);

        $validator = Validator::make($request->all(), $request->rules());
        $this->assertTrue($validator->passes(), 'Precondition: the payload must validate.');

        $request->setValidator($validator);

        $coerced = $request->validated(['biometric_type']);

        $this->assertInstanceOf(BiometricTypeEnum::class, $coerced);
        $this->assertSame(BiometricTypeEnum::Fingerprint, $coerced);
    }

    public function test_validated_still_rejects_an_unrecognised_type(): void
    {
        // Rule::enum — not the override's pass-through branch — is the
        // enforcement point: validated() delegates to the parent, which throws
        // as soon as the validator has failed. This locks in that the override
        // cannot swallow a bad payload and hand the controller a
        // silently-substituted enum. It is also why the override's non-string
        // guard is defence-in-depth rather than a reachable branch.
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create(['gym_id' => $gym->id]);

        $request = BiometricEnrollRequest::create('/api/v1/biometric/enroll', 'POST', [
            'gym_member_id' => $member->id,
            'biometric_type' => 'not-a-real-type',
            'data' => 'sample-data',
        ]);

        $validator = Validator::make($request->all(), $request->rules());
        $this->assertTrue($validator->fails(), 'Precondition: an unknown type must fail the rule.');

        $request->setValidator($validator);

        $this->expectException(ValidationException::class);

        $request->validated('biometric_type');
    }
}
