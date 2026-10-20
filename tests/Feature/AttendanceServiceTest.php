<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Trainer;
use App\Models\User;
use App\Services\AttendanceService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * Pins the `ATTENDANCE_MODELS` allow-list and the guard each public writer runs
 * against it.
 *
 * The guard list is easy to leave stale, so its exact membership is asserted
 * through a reflection read rather than by exercising a caller, and the
 * `User` member (added when the list was widened) is exercised through
 * `markAbsent()` and `generateUserReport()`.
 *
 * Note: the guards' strict `in_array(..., true)` third argument is defensive
 * style, not the protection. `$userType` is typed `string` under
 * `declare(strict_types=1)`, so a non-string never reaches the guard; removing
 * the third argument therefore cannot be caught by a test. The parameter type is
 * the real contract.
 */
class AttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
    }

    public function test_the_attendance_models_const_is_exactly_the_shareable_models(): void
    {
        $reflection = new ReflectionClass(AttendanceService::class);
        $models = $reflection->getConstant('ATTENDANCE_MODELS');

        $this->assertSame(
            [GymMember::class, Trainer::class, User::class],
            $models,
            'An attendance row may only point at these three models; the guard list is what enforces it.'
        );
    }

    public function test_check_in_rejects_a_user_type_outside_the_allow_list(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid user type.');

        AttendanceService::checkIn(1, 'App\\Models\\NotAModel', $this->gym->id);
    }

    public function test_check_in_accepts_an_allow_listed_type_and_proceeds_to_the_existence_check(): void
    {
        // Passing the guard is proven by reaching the *next* failure: the member
        // does not exist, so the "not found" guard fires rather than "Invalid
        // user type".
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User not found or does not belong to this gym.');

        AttendanceService::checkIn(9_999_999, GymMember::class, $this->gym->id);
    }

    public function test_check_out_rejects_a_user_type_outside_the_allow_list(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid user type.');

        AttendanceService::checkOut(1, 'App\\Models\\NotAModel', $this->gym->id);
    }

    public function test_check_out_accepts_an_allow_listed_type_and_proceeds_to_the_active_lookup(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No active check-in found for this user.');

        AttendanceService::checkOut(9_999_999, GymMember::class, $this->gym->id);
    }

    public function test_mark_absent_rejects_a_user_type_outside_the_allow_list(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid user type.');

        AttendanceService::markAbsent(1, 'App\\Models\\NotAModel', $this->gym->id);
    }

    public function test_mark_absent_accepts_an_allow_listed_type_and_proceeds_to_the_existence_check(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User not found or does not belong to this gym.');

        AttendanceService::markAbsent(9_999_999, Trainer::class, $this->gym->id);
    }

    public function test_mark_absent_accepts_the_user_model(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User not found or does not belong to this gym.');

        AttendanceService::markAbsent(9_999_999, User::class, $this->gym->id);
    }

    public function test_check_in_names_archiving_as_the_denial_reason(): void
    {
        $member = GymMember::factory()->create(['gym_id' => $this->gym->id]);
        $member->forceFill(['is_archived' => true, 'archived_at' => now()])->save();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot check in: Your membership has been archived.');

        AttendanceService::checkIn($member->id, GymMember::class, $this->gym->id);
    }

    public function test_check_in_always_names_a_non_empty_reason_for_a_member_with_no_membership_end(): void
    {
        // canAccessGym() denies a null membership_end. The old hand-rolled cascade
        // in AttendanceService had no branch for it and threw the literal
        // 'Cannot check in: ' with an empty reason; the guard's cascade then fell
        // through to the generic catch-all sentence. The new 'no_membership' clause
        // mirrors canAccessGym() and names the cause specifically.
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => null,
            'payment_status' => PaymentStatus::COMPLETED,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot check in: No active membership is on record.');

        AttendanceService::checkIn($member->id, GymMember::class, $this->gym->id);
    }

    public function test_check_in_allows_a_partial_payment_member_with_a_live_membership(): void
    {
        // canAccessGym() — the predicate that actually gates admission, before and
        // after this change — allows a live membership paid to PARTIAL. The old
        // hand-rolled reason cascade (not the gate) was what denied PARTIAL: it only
        // built the failure message, and with no PARTIAL branch it could produce the
        // empty reason. Admission never changed; the guard's single mapping now keeps
        // the denial sentence consistent with the predicate that admitted.
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonth(),
            'membership_end' => now()->addMonth(),
            'payment_status' => PaymentStatus::PARTIAL,
        ]);

        $attendance = AttendanceService::checkIn($member->id, GymMember::class, $this->gym->id);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'attendanceable_id' => $member->id,
            'attendanceable_type' => GymMember::class,
        ]);
    }

    public function test_generate_user_report_rejects_a_user_type_outside_the_allow_list(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid user type.');

        AttendanceService::generateUserReport(1, 'App\\Models\\NotAModel', $this->gym->id);
    }

    public function test_generate_user_report_accepts_an_allow_listed_type(): void
    {
        $report = AttendanceService::generateUserReport(9_999_999, User::class, $this->gym->id);

        $this->assertSame('User', $report['user_type']);
        $this->assertSame(0, $report['present_days']);
        $this->assertSame(0, $report['completed_days']);
    }
}
