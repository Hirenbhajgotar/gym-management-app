<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Member\ArchiveInactiveMembersAction;
use App\Console\Commands\ArchiveInactiveGymMembersCommand;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MembershipPlan;
use App\Services\MemberArchiveService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Behavioural guarantees the happy-path archive tests do not reach: attribute
 * isolation on the write, the skipped counter actually moving, a chunk that
 * dies still being reported with its progress, and the ADMS credential being
 * revoked on the same policy archiving claims to enforce everywhere else.
 */
class MemberArchiveIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected MemberArchiveService $archiveService;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->gym = Gym::factory()->create();
        $this->archiveService = resolve(MemberArchiveService::class);
    }

    private function staleMember(): GymMember
    {
        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->subYears(3)->toDateString(),
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        return $member;
    }

    /**
     * Bind a service double so archive() can be made to refuse or throw on a
     * chosen call — the only way to reach the action's counting and failure
     * paths deterministically. Every other method keeps real behaviour.
     */
    private function bindServiceWithArchiveCalls(callable $onArchive): void
    {
        $real = $this->archiveService;

        $mock = Mockery::mock(MemberArchiveService::class)->makePartial();
        $mock->shouldReceive('archivableMembers')->andReturnUsing(
            fn (int $years = 2, ?int $gymId = null): Builder => $real->archivableMembers($years, $gymId)
        );
        $mock->shouldReceive('cutoffDate')->andReturnUsing(
            fn (int $years = 2): Carbon => $real->cutoffDate($years)
        );
        $mock->shouldReceive('archiveAsSystem')->andReturnUsing(
            fn (GymMember $member): bool => (bool) $onArchive($real, $member)
        );

        $this->app->instance(MemberArchiveService::class, $mock);
        $this->app->instance(
            ArchiveInactiveMembersAction::class,
            new ArchiveInactiveMembersAction($mock)
        );
    }

    public function test_archive_persists_only_the_four_archive_columns(): void
    {
        $member = $this->staleMember();
        $originalName = $member->name;
        $originalPhone = $member->phone;

        // An unrelated edit the caller happens to be holding. archive() must not
        // persist it: the write is scoped to the archive columns, and the
        // caller's copy is replaced by what was actually stored.
        $member->name = 'Renamed By A Stray Caller';
        $member->phone = '+919876500099';

        $this->assertTrue($this->archiveService->archiveAsSystem($member));

        $stored = $member->fresh();

        $this->assertNotNull($stored);
        $this->assertSame($originalName, $stored->name);
        $this->assertSame($originalPhone, $stored->phone);
        $this->assertTrue($stored->is_archived);
    }

    public function test_unarchive_persists_only_the_four_archive_columns(): void
    {
        /** @var GymMember $member */
        $member = GymMember::factory()->archived(true)->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->subYears(3)->toDateString(),
        ]);

        $originalName = $member->name;
        $member->name = 'Renamed By A Stray Caller';

        $this->assertTrue($this->archiveService->unarchiveAsSystem($member));

        $stored = $member->fresh();

        $this->assertNotNull($stored);
        $this->assertSame($originalName, $stored->name);
        $this->assertFalse($stored->is_archived);
    }

    public function test_archive_leaves_the_caller_instance_synced_and_clean(): void
    {
        $member = $this->staleMember();

        $this->assertTrue($this->archiveService->archiveAsSystem($member));

        // No refresh() anywhere in this test: the caller must already hold the
        // persisted row with a matching original set, so a later save() is not a
        // re-write of values the database has since moved on from.
        $this->assertTrue($member->is_archived);
        $this->assertFalse($member->isDirty());
    }

    public function test_action_counts_a_refused_archive_as_skipped(): void
    {
        GymMember::factory()->count(3)->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->subYears(3)->toDateString(),
            'is_archived' => false,
        ]);

        $calls = 0;

        $this->bindServiceWithArchiveCalls(function (MemberArchiveService $real, GymMember $member) use (&$calls): bool {
            $calls++;

            // One row is taken by another writer between selection and write.
            return $calls !== 2 && $real->archiveAsSystem($member);
        });

        $result = resolve(ArchiveInactiveMembersAction::class)->execute(2);

        $this->assertSame(2, $result->archivedCount);
        $this->assertSame(1, $result->skippedCount);
        $this->assertSame(3, $result->totalEligible);
    }

    public function test_command_warns_when_members_were_skipped(): void
    {
        GymMember::factory()->count(2)->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->subYears(3)->toDateString(),
            'is_archived' => false,
        ]);

        $this->bindServiceWithArchiveCalls(fn (): bool => false);

        $this->artisan(ArchiveInactiveGymMembersCommand::class)
            ->expectsOutputToContain('Successfully archived 0 inactive member(s).')
            ->expectsOutputToContain('2 eligible member(s) were archived concurrently and skipped.')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_a_failed_member_is_contained_and_the_run_continues(): void
    {
        // CHUNK_SIZE is 100, so 101 eligible members spans two chunks; the very
        // last member throws. Members are archived one at a time, so a single
        // member's failure is contained: the 100 already-committed members stay
        // archived, the failure is logged with the member it belongs to, and the
        // run still reaches its success exit with failed_count naming the one
        // that did not make it.
        GymMember::factory()->count(101)->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->subYears(3)->toDateString(),
            'is_archived' => false,
        ]);

        $calls = 0;

        $this->bindServiceWithArchiveCalls(function (MemberArchiveService $real, GymMember $member) use (&$calls): bool {
            $calls++;

            if ($calls === 101) {
                throw new RuntimeException('member exploded');
            }

            return $real->archiveAsSystem($member);
        });

        Log::spy();

        $result = resolve(ArchiveInactiveMembersAction::class)->execute(2);

        $this->assertSame(101, $result->totalEligible);
        $this->assertSame(100, $result->archivedCount);
        $this->assertSame(0, $result->skippedCount);

        Log::shouldHaveReceived('error')
            ->atLeast()
            ->once()
            ->with('ArchiveInactiveMembersAction: failed to archive a member; continuing', Mockery::on(
                fn (array $context): bool => $context['error_type'] === RuntimeException::class
            ));

        Log::shouldHaveReceived('info')
            ->atLeast()
            ->once()
            ->with('ArchiveInactiveMembersAction executed', Mockery::on(
                fn (array $context): bool => $context['years_cutoff'] === 2
                    && $context['total_eligible'] === 101
                    && $context['archived_count'] === 100
                    && $context['skipped_count'] === 0
                    && $context['failed_count'] === 1
            ));

        // The committed members really are durable, which is why the run can
        // finish rather than roll them back.
        $this->assertSame(100, GymMember::query()->archived()->count());
    }

    public function test_a_failing_member_contributes_nothing_to_the_reported_totals(): void
    {
        GymMember::factory()->count(3)->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->subYears(3)->toDateString(),
            'is_archived' => false,
        ]);

        $this->bindServiceWithArchiveCalls(function (): bool {
            throw new RuntimeException('member exploded');
        });

        Log::spy();

        $result = resolve(ArchiveInactiveMembersAction::class)->execute(2);

        $this->assertSame(3, $result->totalEligible);
        $this->assertSame(0, $result->archivedCount);
        $this->assertSame(0, $result->skippedCount);

        Log::shouldHaveReceived('error')
            ->times(3)
            ->with('ArchiveInactiveMembersAction: failed to archive a member; continuing', Mockery::on(
                fn (array $context): bool => $context['error_type'] === RuntimeException::class
            ));

        $this->assertSame(0, GymMember::query()->archived()->count());
    }

    public function test_archivable_members_can_be_scoped_to_one_gym(): void
    {
        $otherGym = Gym::factory()->create();

        $mine = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->subYears(3)->toDateString(),
            'is_archived' => false,
        ]);

        $theirs = GymMember::factory()->create([
            'gym_id' => $otherGym->id,
            'membership_end' => now()->subYears(3)->toDateString(),
            'is_archived' => false,
        ]);

        // The scheduled command is platform-wide and passes null explicitly.
        $platformWide = $this->archiveService->archivableMembers(2)->pluck('id');
        $this->assertTrue($platformWide->contains($mine->id));
        $this->assertTrue($platformWide->contains($theirs->id));

        $scoped = $this->archiveService->archivableMembers(2, $this->gym->id)->pluck('id');
        $this->assertTrue($scoped->contains($mine->id));
        $this->assertFalse($scoped->contains($theirs->id));
    }

    public function test_the_cleanup_sweep_revokes_the_hardware_credential_of_an_archived_member(): void
    {
        $member = GymMember::factory()->archived()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->subYears(3)->toDateString(),
        ]);

        // A device is bound and the provider type is an explicit hardware value:
        // with neither, the cloud/native fork was driven by the factory's random
        // provider_type and the test was not deterministic.
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
        ]);

        $template = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member->id,
            'enrolled_by_device_id' => $device->id,
            'provider_type' => 'device_native',
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        $this->assertTrue((bool) $template->is_active);

        // Archiving is an access revocation, so the credential that still opens
        // the door must not survive it. This sweep is the retry net for an
        // archived member whose revocation could not complete at archive time.
        $this->artisan('app:cleanup-expired-device-members')
            ->assertExitCode(Command::SUCCESS);

        $this->assertFalse(
            (bool) $template->fresh()?->is_active,
            'An archived member must not keep an active biometric credential.'
        );

        $this->assertDatabaseHas('adms_device_commands', [
            'device_id' => $device->id,
            'command_type' => 'user_delete',
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);
    }

    public function test_archiving_a_member_with_a_cloud_template_queues_no_hardware_delete(): void
    {
        // The archive service must delegate the cloud-vs-hardware decision. A
        // cloud credential is not on the terminal, so archiving must not queue a
        // PIN-scoped `user_delete` that a terminal ACK would later converge to
        // Deleted while the provider template survives.
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addYear()->toDateString(),
        ]);

        $device = BiometricDevice::factory()->dualMode()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
        ]);

        $cloud = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member->id,
            'enrolled_by_device_id' => $device->id,
            'provider_type' => 'unconfigured_cloud_provider',
            'template_ref' => '{"provider":"unconfigured_cloud_provider","externalId":"face-1","meta":[]}',
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        $this->assertTrue($this->archiveService->archiveAsSystem($member));

        $this->assertDatabaseMissing('adms_device_commands', [
            'device_id' => $device->id,
            'command_type' => 'user_delete',
        ]);

        $cloud->refresh();
        $this->assertFalse((bool) $cloud->is_active);
        $this->assertSame(
            BiometricTemplateSyncStatusEnum::PendingDeletion,
            $cloud->sync_status,
            'A cloud row must be parked for the provider, never marked Deleted by a hardware path.'
        );
    }

    public function test_archiving_revokes_a_pending_mid_enrolment_placeholder(): void
    {
        // The revocation leg. A mid-enrolment placeholder is inactive yet
        // already has its user_add queued, so a filter on is_active would leave
        // the credential to be promoted back to live on hardware ACK — the exact
        // "the app says revoked, the terminal still holds the credential" state
        // archiving declares impossible. The revocation must include the Pending
        // placeholder, cancel its queued user_add, and leave no active template.
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addYear()->toDateString(),
            'is_archived' => false,
        ]);

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
        ]);

        $template = BiometricTemplate::factory()->pending()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member->id,
            'enrolled_by_device_id' => $device->id,
        ]);

        $command = AdmsDeviceCommand::factory()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $member->id, 'Name' => $member->name],
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        $this->assertTrue($this->archiveService->archiveAsSystem($member));

        $template->refresh();
        $command->refresh();

        $this->assertFalse(
            (bool) $template->is_active,
            'A mid-enrolment placeholder must not survive archiving as a live credential.'
        );
        // The placeholder never reached the hardware, so erasing it cancels the
        // queued user_add and converges to Deleted rather than parking for a
        // terminal that never held it.
        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $template->sync_status);
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $command->status);
    }

    public function test_cleanup_still_sweeps_an_ordinary_expired_member(): void
    {
        // The control for the test above: including archived members must not
        // have narrowed the command to archived members only.
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'duration' => 6,
        ]);

        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_membership_plan_id' => $plan->id,
            'membership_end' => now()->subYear()->toDateString(),
            'is_archived' => false,
        ]);

        $template = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member->id,
            'is_active' => true,
        ]);

        $this->artisan('app:cleanup-expired-device-members')
            ->assertExitCode(Command::SUCCESS);

        $this->assertFalse((bool) $template->fresh()?->is_active);
    }

    public function test_a_live_member_is_not_swept_by_the_cleanup(): void
    {
        // The other side of the boundary: the cleanup keys on the expiry cutoff,
        // and widening it to archived members must not have widened it to
        // everyone.
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addYear()->toDateString(),
            'is_archived' => false,
        ]);

        $template = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member->id,
            'is_active' => true,
        ]);

        $this->artisan('app:cleanup-expired-device-members')
            ->assertExitCode(Command::SUCCESS);

        $this->assertTrue((bool) $template->fresh()?->is_active);
    }

    public function test_the_command_scope_and_the_reported_cutoff_agree_at_the_boundary(): void
    {
        // The candidate filter is `membership_end < cutoff`, and the header
        // reports that same cutoff. Assert BOTH sides of the boundary against
        // the real command rather than re-implementing cutoffDate(): a member
        // whose end is the day before the cutoff is archived; one whose end is
        // ON the cutoff day is not.
        $cutoff = $this->archiveService->cutoffDate(2);

        $beforeCutoff = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => $cutoff->copy()->subDay()->toDateString(),
            'is_archived' => false,
        ]);

        $onCutoff = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => $cutoff->toDateString(),
            'is_archived' => false,
        ]);

        $this->artisan(ArchiveInactiveGymMembersCommand::class)
            ->expectsOutputToContain('prior to ' . $cutoff->toDateString())
            ->assertExitCode(Command::SUCCESS);

        $this->assertTrue(
            (bool) $beforeCutoff->fresh()?->is_archived,
            'A member one day before the cutoff must be archived.'
        );
        $this->assertFalse(
            (bool) $onCutoff->fresh()?->is_archived,
            'A member exactly on the cutoff day must not be archived (the filter is < cutoff).'
        );
    }

    public function test_record_consent_while_archived_is_a_no_op_for_a_live_member(): void
    {
        // The guard the importers depend on: this method only owns consent for
        // an ARCHIVED member. On a live member it must return false and write
        // nothing, so a caller can fall back to an ordinary column write.
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => false,
            'whatsapp_marketing_snapshot' => null,
        ]);

        $this->assertFalse(
            $this->archiveService->recordConsentWhileArchived($member, true),
            'A live member is not this method\'s responsibility.'
        );

        $fresh = $member->fresh();

        $this->assertNotNull($fresh);
        $this->assertFalse((bool) $fresh->allow_whatsapp_marketing);
        $this->assertNull($fresh->whatsapp_marketing_snapshot);
    }
}
