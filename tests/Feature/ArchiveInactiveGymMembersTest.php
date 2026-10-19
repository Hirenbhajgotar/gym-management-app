<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Member\ArchiveInactiveMembersAction;
use App\Biometrics\Services\MembershipGuard;
use App\Console\Commands\ArchiveInactiveGymMembersCommand;
use App\Enums\PaymentStatus;
use App\Models\Gym;
use App\Models\GymMember;
use App\Services\Biometric\BiometricConsentEligibility;
use App\Services\MemberArchiveService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ArchiveInactiveGymMembersTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected MemberArchiveService $archiveService;

    protected ArchiveInactiveMembersAction $archiveAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->archiveService = resolve(MemberArchiveService::class);
        $this->archiveAction = resolve(ArchiveInactiveMembersAction::class);
    }

    public function test_service_archives_member_and_disables_whatsapp_marketing(): void
    {
        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $result = $this->archiveService->archiveAsSystem($member);

        $this->assertTrue($result);
        $member->refresh();

        $this->assertTrue($member->is_archived);
        $this->assertNotNull($member->archived_at);
        $this->assertFalse($member->allow_whatsapp_marketing);
        $this->assertTrue($member->whatsapp_marketing_snapshot);
    }

    public function test_service_does_not_rearchive_already_archived_member(): void
    {
        /** @var GymMember $member */
        $member = GymMember::factory()->archived()->create([
            'gym_id' => $this->gym->id,
        ]);

        $result = $this->archiveService->archiveAsSystem($member);

        $this->assertFalse($result);
    }

    public function test_service_unarchives_member_and_restores_whatsapp_marketing(): void
    {
        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $this->archiveService->archiveAsSystem($member);
        $member->refresh();
        $this->assertTrue($member->is_archived);

        $result = $this->archiveService->unarchiveAsSystem($member);

        $this->assertTrue($result);
        $member->refresh();

        $this->assertFalse($member->is_archived);
        $this->assertNull($member->archived_at);
        $this->assertTrue($member->allow_whatsapp_marketing);
        $this->assertNull($member->whatsapp_marketing_snapshot);
    }

    public function test_service_does_not_unarchive_non_archived_member(): void
    {
        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'is_archived' => false,
        ]);

        $result = $this->archiveService->unarchiveAsSystem($member);

        $this->assertFalse($result);
    }

    public function test_service_archivable_members_filters_only_stale_records(): void
    {
        // Stale member expired 2.5 years ago
        $staleMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->subYears(2)->subMonths(6)->toDateString(),
            'is_archived' => false,
        ]);

        // Recent member expired 6 months ago
        $recentMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->subMonths(6)->toDateString(),
            'is_archived' => false,
        ]);

        // Already archived member
        $alreadyArchived = GymMember::factory()->archived()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->subYears(3)->toDateString(),
        ]);

        // Member without membership_end
        $noEndDate = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => null,
            'is_archived' => false,
        ]);

        $eligibleIds = $this->archiveService->archivableMembers(2)->pluck('id');

        $this->assertTrue($eligibleIds->contains($staleMember->id));
        $this->assertFalse($eligibleIds->contains($recentMember->id));
        $this->assertFalse($eligibleIds->contains($alreadyArchived->id));
        $this->assertFalse($eligibleIds->contains($noEndDate->id));
    }

    public function test_action_archives_eligible_members_and_returns_counts(): void
    {
        GymMember::factory()->count(3)->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->subYears(3)->toDateString(),
            'is_archived' => false,
        ]);

        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->subMonths(3)->toDateString(),
            'is_archived' => false,
        ]);

        $result = $this->archiveAction->execute(2);

        $this->assertSame(3, $result->archivedCount);
        $this->assertSame(3, $result->totalEligible);
        $this->assertSame(0, $result->skippedCount);

        $this->assertSame(1, GymMember::query()->notArchived()->where('gym_id', $this->gym->id)->count());
        $this->assertSame(4, GymMember::query()->where('gym_id', $this->gym->id)->count());
    }

    public function test_action_returns_zero_when_no_members_are_eligible(): void
    {
        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->addMonth()->toDateString(),
            'is_archived' => false,
        ]);

        $result = $this->archiveAction->execute(2);

        $this->assertSame(0, $result->archivedCount);
        $this->assertSame(0, $result->totalEligible);
        $this->assertSame(0, $result->skippedCount);
    }

    public function test_archiving_hides_a_member_from_the_roster_scope_but_not_from_plain_queries(): void
    {
        $activeMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'is_archived' => false,
        ]);

        $toBeArchived = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'is_archived' => false,
            'membership_end' => Carbon::now()->subYears(3)->toDateString(),
        ]);

        $alreadyArchived = GymMember::factory()->archived()->create([
            'gym_id' => $this->gym->id,
        ]);

        $this->assertTrue($this->archiveService->archiveAsSystem($toBeArchived));

        // A plain query sees the whole history, archived members included:
        // their payments, invoices and attendance still have to resolve to a
        // person. Visibility of the current roster is opt-in through
        // notArchived(), never a default — and archiving itself changes nothing
        // about that, which is what makes archiving reversible.
        $allMembers = GymMember::query()->where('gym_id', $this->gym->id)->pluck('id');
        $this->assertTrue($allMembers->contains($activeMember->id));
        $this->assertTrue($allMembers->contains($toBeArchived->id));
        $this->assertTrue($allMembers->contains($alreadyArchived->id));

        $notArchived = GymMember::query()->notArchived()->where('gym_id', $this->gym->id)->pluck('id');
        $this->assertTrue($notArchived->contains($activeMember->id));
        $this->assertFalse($notArchived->contains($toBeArchived->id));
        $this->assertFalse($notArchived->contains($alreadyArchived->id));

        $onlyArchived = GymMember::query()->archived()->where('gym_id', $this->gym->id)->pluck('id');
        $this->assertFalse($onlyArchived->contains($activeMember->id));
        $this->assertTrue($onlyArchived->contains($toBeArchived->id));
        $this->assertTrue($onlyArchived->contains($alreadyArchived->id));
    }

    public function test_command_archives_inactive_members_successfully(): void
    {
        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->subYears(3)->toDateString(),
            'is_archived' => false,
        ]);

        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->subMonths(6)->toDateString(),
            'is_archived' => false,
        ]);

        $this->artisan(ArchiveInactiveGymMembersCommand::class)
            ->expectsOutputToContain('Searching for inactive members')
            ->expectsOutputToContain('Successfully archived 1 inactive member(s)')
            ->assertExitCode(0);
    }

    public function test_command_outputs_no_members_found_when_none_eligible(): void
    {
        $this->artisan(ArchiveInactiveGymMembersCommand::class)
            ->expectsOutputToContain('No inactive members found matching archive criteria.')
            ->assertExitCode(0);
    }

    public function test_command_supports_custom_years_option(): void
    {
        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->subMonths(18)->toDateString(),
            'is_archived' => false,
        ]);

        // Default 2 years: shouldn't archive (18 months < 24 months)
        $this->artisan('app:archive-inactive-gym-members')
            ->expectsOutputToContain('No inactive members found matching archive criteria.')
            ->assertExitCode(0);

        // Custom 1 year: should archive (18 months > 12 months)
        $this->artisan('app:archive-inactive-gym-members', ['--years' => 1])
            ->expectsOutputToContain('Successfully archived 1 inactive member(s)')
            ->assertExitCode(0);
    }

    public function test_archivable_members_excludes_a_membership_ending_exactly_on_the_cutoff(): void
    {
        // The comparison is strict '<' against the cutoff's start of day, so a
        // membership ending on the cutoff instant itself is not yet archivable.
        $onCutoff = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => $this->archiveService->cutoffDate(2)->toDateString(),
            'is_archived' => false,
        ]);

        $beforeCutoff = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => $this->archiveService->cutoffDate(2)->subDay()->toDateString(),
            'is_archived' => false,
        ]);

        $eligibleIds = $this->archiveService->archivableMembers(2)->pluck('id');

        $this->assertFalse($eligibleIds->contains($onCutoff->id));
        $this->assertTrue($eligibleIds->contains($beforeCutoff->id));
    }

    public function test_second_run_archives_nothing(): void
    {
        GymMember::factory()->count(2)->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->subYears(3)->toDateString(),
            'is_archived' => false,
        ]);

        $first = $this->archiveAction->execute(2);
        $second = $this->archiveAction->execute(2);

        $this->assertSame(2, $first->archivedCount);
        $this->assertSame(0, $second->archivedCount);
        $this->assertSame(0, $second->totalEligible);
        $this->assertSame(2, GymMember::query()->archived()->where('gym_id', $this->gym->id)->count());
    }

    public function test_unarchive_falls_back_to_current_consent_when_no_snapshot_was_stored(): void
    {
        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => false,
        ]);

        $this->archiveService->archiveAsSystem($member);
        $member->refresh();

        // Simulate a row archived before snapshots existed.
        $member->forceFill(['whatsapp_marketing_snapshot' => null])->save();

        $this->assertTrue($this->archiveService->unarchiveAsSystem($member));

        $member->refresh();
        $this->assertFalse($member->allow_whatsapp_marketing);
        $this->assertNull($member->whatsapp_marketing_snapshot);
    }

    public function test_archived_member_is_denied_gym_access_even_while_their_membership_is_live(): void
    {
        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->addYear()->toDateString(),
            'payment_status' => PaymentStatus::COMPLETED,
            'is_archived' => false,
        ]);

        $this->assertTrue($member->canAccessGym());

        $this->archiveService->archiveAsSystem($member);
        $member->refresh();

        // Revocation is a property of the member, so it holds however the row was
        // loaded — including a query that does not filter archived members.
        $this->assertFalse($member->canAccessGym());
    }

    public function test_membership_guard_denial_for_an_archived_member_names_the_archive_state(): void
    {
        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->addYear()->toDateString(),
            'payment_status' => PaymentStatus::COMPLETED,
            'is_archived' => false,
        ]);

        $this->archiveService->archiveAsSystem($member);
        $member->refresh();

        $result = resolve(MembershipGuard::class)->canCheckIn($member);

        $this->assertFalse($result->allowed);
        $this->assertSame('member_archived', $result->reason);
    }

    public function test_biometric_consent_eligibility_excludes_archived_members_in_both_entry_points(): void
    {
        $eligible = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->addMonths(6)->toDateString(),
            'is_archived' => false,
        ]);

        $archived = GymMember::factory()->archived()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->addMonths(6)->toDateString(),
        ]);

        $constrained = BiometricConsentEligibility::constrain(
            GymMember::query()->where('gym_id', $this->gym->id),
            $this->gym->id
        )->pluck('id');

        $this->assertTrue($constrained->contains($eligible->id));
        $this->assertFalse($constrained->contains($archived->id));
        $this->assertTrue(BiometricConsentEligibility::isEligible($eligible));
        $this->assertFalse(BiometricConsentEligibility::isEligible($archived));
    }

    public function test_action_logs_the_zero_eligible_run_instead_of_returning_silently(): void
    {
        Log::spy();

        $result = $this->archiveAction->execute(2);

        $this->assertSame(0, $result->totalEligible);

        // A scheduled run that found nothing is evidence the schedule fired.
        // Before, this path returned before the only Log::info, so silence was
        // indistinguishable from the job never running.
        Log::shouldHaveReceived('info')
            ->atLeast()
            ->once()
            ->with('ArchiveInactiveMembersAction executed', [
                'years_cutoff' => 2,
                'total_eligible' => 0,
                'archived_count' => 0,
                'skipped_count' => 0,
                'failed_count' => 0,
            ]);
    }

    public function test_action_logs_a_non_zero_run_with_its_own_totals(): void
    {
        GymMember::factory()->count(2)->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->subYears(3)->toDateString(),
            'is_archived' => false,
        ]);

        Log::spy();

        $this->archiveAction->execute(2);

        Log::shouldHaveReceived('info')
            ->atLeast()
            ->once()
            ->with('ArchiveInactiveMembersAction executed', [
                'years_cutoff' => 2,
                'total_eligible' => 2,
                'archived_count' => 2,
                'skipped_count' => 0,
                'failed_count' => 0,
            ]);
    }

    public function test_action_totals_stay_correct_across_chunk_boundaries(): void
    {
        // ArchiveInactiveMembersAction::CHUNK_SIZE is 100, so 101 eligible
        // members forces a second chunk and exercises the per-chunk totals.
        $eligible = 101;

        GymMember::factory()->count($eligible)->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->subYears(3)->toDateString(),
            'is_archived' => false,
        ]);

        $result = $this->archiveAction->execute(2);

        $this->assertSame($eligible, $result->totalEligible);
        $this->assertSame($eligible, $result->archivedCount);
        $this->assertSame(0, $result->skippedCount);

        $this->assertSame(
            $eligible,
            GymMember::query()->archived()->where('gym_id', $this->gym->id)->count()
        );
    }

    public function test_service_archive_refuses_a_row_a_concurrent_writer_already_archived(): void
    {
        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        // A second writer archives the row while our in-memory copy still says
        // the member is active — exactly the window a chunk callback runs in.
        $archivedByOtherWriter = Carbon::now()->subMinute()->startOfSecond();

        GymMember::query()->whereKey($member->getKey())->update([
            'is_archived' => true,
            'archived_at' => $archivedByOtherWriter,
            'allow_whatsapp_marketing' => false,
            'whatsapp_marketing_snapshot' => true,
        ]);

        $this->assertFalse($this->archiveService->archiveAsSystem($member));

        $member->refresh();

        // Neither archived_at nor the consent snapshot may be overwritten: a
        // re-archive here would restamp archived_at and re-snapshot the already
        // forced false consent, permanently losing the real one.
        $this->assertTrue($member->is_archived);
        $this->assertTrue($member->archived_at->equalTo($archivedByOtherWriter));
        // The concurrent writer already forced marketing off, so off is the
        // value that must survive — the snapshot holding `true` is the record of
        // what the member had actually consented to.
        $this->assertFalse($member->allow_whatsapp_marketing);
        $this->assertTrue($member->whatsapp_marketing_snapshot);
    }

    public function test_service_archive_is_idempotent_when_the_same_instance_is_passed_twice(): void
    {
        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $this->assertTrue($this->archiveService->archiveAsSystem($member));
        $this->assertFalse($this->archiveService->archiveAsSystem($member));

        $member->refresh();

        $this->assertTrue($member->is_archived);
        $this->assertNotNull($member->archived_at);
        $this->assertFalse($member->allow_whatsapp_marketing);
        $this->assertTrue($member->whatsapp_marketing_snapshot);
    }

    public function test_service_archive_leaves_the_caller_instance_in_sync(): void
    {
        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $this->assertTrue($this->archiveService->archiveAsSystem($member));

        // No refresh() anywhere in this test: the caller's own instance must
        // already carry the persisted state, including a synced original set
        // so a later save() is not a no-op re-write.
        $this->assertTrue($member->is_archived);
        $this->assertNotNull($member->archived_at);
        $this->assertFalse($member->allow_whatsapp_marketing);
        $this->assertTrue($member->whatsapp_marketing_snapshot);
        $this->assertFalse($member->isDirty());
    }

    public function test_service_unarchive_refuses_a_row_a_concurrent_writer_already_restored(): void
    {
        /** @var GymMember $member */
        $member = GymMember::factory()->archived(true)->create([
            'gym_id' => $this->gym->id,
        ]);

        // Another writer unarchived and re-consented the row underneath us.
        GymMember::query()->whereKey($member->getKey())->update([
            'is_archived' => false,
            'archived_at' => null,
            'allow_whatsapp_marketing' => true,
            'whatsapp_marketing_snapshot' => null,
        ]);

        $this->assertFalse($this->archiveService->unarchiveAsSystem($member));

        $member->refresh();

        $this->assertFalse($member->is_archived);
        $this->assertNull($member->archived_at);
        $this->assertTrue($member->allow_whatsapp_marketing);
        $this->assertNull($member->whatsapp_marketing_snapshot);
    }

    public function test_service_unarchive_leaves_the_caller_instance_in_sync(): void
    {
        /** @var GymMember $member */
        $member = GymMember::factory()->archived(true)->create([
            'gym_id' => $this->gym->id,
        ]);

        $this->assertTrue($this->archiveService->unarchiveAsSystem($member));

        $this->assertFalse($member->is_archived);
        $this->assertNull($member->archived_at);
        $this->assertTrue($member->allow_whatsapp_marketing);
        $this->assertNull($member->whatsapp_marketing_snapshot);
        $this->assertFalse($member->isDirty());
    }

    public function test_command_does_not_warn_about_concurrent_skips_on_a_clean_run(): void
    {
        GymMember::factory()->count(3)->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->subYears(3)->toDateString(),
            'is_archived' => false,
        ]);

        // skippedCount is 0, so no skip warning may be printed. The command
        // gates the warning on skippedCount alone rather than on any
        // archived-vs-eligible comparison, so the "0 eligible member(s) were
        // archived concurrently and skipped." wording is unreachable here.
        $this->artisan('app:archive-inactive-gym-members')
            ->doesntExpectOutputToContain('archived concurrently and skipped')
            ->expectsOutputToContain('Successfully archived 3 inactive member(s)')
            ->assertExitCode(0);
    }

    public function test_command_rejects_a_years_option_below_one(): void
    {
        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->subYears(3)->toDateString(),
            'is_archived' => false,
        ]);

        $this->artisan('app:archive-inactive-gym-members', ['--years' => 0])
            ->expectsOutputToContain('must be a whole number of 1 or greater')
            ->assertExitCode(Command::FAILURE);

        // Nothing was archived: a 0-year cutoff must not mass-archive every
        // expired member in every gym.
        $this->assertSame(0, GymMember::query()->archived()->count());
        $this->assertSame(1, GymMember::query()->notArchived()->count());
    }

    public function test_command_rejects_a_non_numeric_years_option(): void
    {
        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->subYears(3)->toDateString(),
            'is_archived' => false,
        ]);

        // 'abc' cast to int is 0, which would archive every expired member.
        $this->artisan('app:archive-inactive-gym-members', ['--years' => 'abc'])
            ->expectsOutputToContain('must be a whole number of 1 or greater')
            ->assertExitCode(Command::FAILURE);

        $this->assertSame(0, GymMember::query()->archived()->count());
    }

    public function test_command_dry_run_reports_without_archiving(): void
    {
        GymMember::factory()->count(3)->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->subYears(3)->toDateString(),
            'is_archived' => false,
        ]);

        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->subMonths(6)->toDateString(),
            'is_archived' => false,
        ]);

        $this->artisan('app:archive-inactive-gym-members', ['--dry-run' => true])
            ->expectsOutputToContain('Searching for inactive members')
            ->expectsOutputToContain('3 inactive member(s) would be archived')
            ->assertExitCode(0);

        $this->assertSame(0, GymMember::query()->archived()->count());
        $this->assertSame(4, GymMember::query()->notArchived()->count());
    }

    public function test_command_dry_run_reports_nothing_to_do_when_none_are_eligible(): void
    {
        $this->artisan('app:archive-inactive-gym-members', ['--dry-run' => true])
            ->expectsOutputToContain('No inactive members found matching archive criteria.')
            ->assertExitCode(0);
    }

    public function test_command_dry_run_reports_the_cutoff_a_real_run_would_use(): void
    {
        // Two memberships that are older than both cutoffs, plus one that falls
        // between them, so the reported count can only be right if it actually
        // follows --years instead of being a fixed number.
        GymMember::factory()->count(2)->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->subYears(3)->toDateString(),
            'is_archived' => false,
        ]);

        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => Carbon::now()->subYear()->subMonths(6)->toDateString(),
            'is_archived' => false,
        ]);

        // A 1-year cutoff is more recent than all three, so all three qualify —
        // and the reported date must be the one that same --years filters on,
        // proving dry-run and a real run agree on which members were in scope.
        $this->artisan('app:archive-inactive-gym-members', ['--years' => 1, '--dry-run' => true])
            ->expectsOutputToContain(
                $this->archiveService->cutoffDate(1)->toDateString()
            )
            ->expectsOutputToContain('3 inactive member(s) would be archived')
            ->assertExitCode(0);

        $this->assertSame(3, $this->archiveService->archivableMembers(1)->count());
        // The 18-month-old membership is inside a 1-year cutoff but outside the
        // 2-year default, so the count genuinely has to shrink with --years.
        $this->assertSame(2, $this->archiveService->archivableMembers(2)->count());
        $this->assertSame(0, GymMember::query()->archived()->count());
    }
}
