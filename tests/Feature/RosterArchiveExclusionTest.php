<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\GymMember;
use App\Services\AttendanceService;
use App\Services\ChurnRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The roster-facing consumers of `notArchived()`.
 *
 * The scope itself is asserted in GymMemberArchivingTest; these cases assert it
 * is actually applied at each consumer, so deleting a filter from a service
 * cannot leave the suite green.
 */
class RosterArchiveExclusionTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);
    }

    public function test_the_member_attendance_summary_counts_only_the_live_roster(): void
    {
        $live = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
        ]);

        $archived = GymMember::factory()->archived()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
        ]);

        $summary = AttendanceService::getGymMemberAttendanceSummary($this->gym->id);

        /** @var array<int, int> $ids */
        $ids = array_column($summary, 'member_id');

        $this->assertContains($live->id, $ids);
        $this->assertNotContains(
            $archived->id,
            $ids,
            'An archived member must not appear on the live-roster attendance summary.'
        );
    }

    public function test_members_not_checked_in_today_excludes_archived_members(): void
    {
        $live = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
        ]);

        $archived = GymMember::factory()->archived()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
        ]);

        $ids = AttendanceService::getMembersNotCheckedInToday($this->gym->id)->pluck('id');

        $this->assertTrue($ids->contains($live->id));
        $this->assertFalse(
            $ids->contains($archived->id),
            'An archived member must not be chased for a daily check-in.'
        );
    }

    public function test_churn_statistics_exclude_archived_members(): void
    {
        // Both members share the same risk shape (no recent attendance, a
        // membership expiring inside 30 days), so the archive flag is the single
        // variable between them.
        $live = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addDays(10),
            'is_archived' => false,
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(60),
        ]);

        GymMember::factory()->archived()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addDays(10),
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(60),
        ]);

        $stats = resolve(ChurnRiskService::class)->getChurnStats($this->gym->id);

        $this->assertSame(
            1,
            $stats['total_at_risk'],
            'Only the live member may be counted in churn statistics.'
        );

        $atRisk = resolve(ChurnRiskService::class)->getAtRiskMembers($this->gym->id, 0);

        $this->assertSame([$live->id], $atRisk->pluck('id')->all());
    }
}
