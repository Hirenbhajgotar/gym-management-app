<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\BiometricDataDeletionService;
use App\Biometrics\Services\BiometricDeProvisioner;
use App\Biometrics\Services\BiometricTemplatePropagator;
use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDeProvisionOutcome;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Enums\DefaultRole;
use App\Events\BiometricConsentRevoked;
use App\Filament\Admin\Actions\EnrollBiometricAction;
use App\Listeners\HandleConsentRevoked;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Role;
use App\Models\User;
use App\Services\MemberArchiveService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Coverage for the erasure hardening this round: the dual-mode provider contract, the
 * erasure-obligation marker vs the sibling fan-out, the cloud attempt budget, the
 * `user_delete` convergence codes, and the archive → ACK → re-enrol → archive cycle.
 */
class BiometricErasureHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected BiometricDevice $deviceA;

    protected BiometricDevice $deviceB;

    protected GymMember $member;

    protected function setUp(): void
    {
        parent::setUp();

        // The cloud branch must never reach a real provider endpoint.
        Http::preventStrayRequests();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        $this->deviceA = BiometricDevice::factory()->admsEnabled('SN-A')->create([
            'gym_id' => $this->gym->id,
            'device_type' => 'fingerprint',
        ]);

        $this->deviceB = BiometricDevice::factory()->admsEnabled('SN-B')->create([
            'gym_id' => $this->gym->id,
            'device_type' => 'fingerprint',
        ]);

        $this->member = GymMember::factory()->create(['gym_id' => $this->gym->id]);

        BiometricConsent::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
            'revoked_at' => null,
        ]);
    }

    protected function gymOwner(): User
    {
        $role = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');

        $owner = User::factory()->create();
        $this->gym->users()->attach($owner->id, ['role' => DefaultRole::GYM_OWNER->value]);
        $owner->assignRole($role);

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        return $owner;
    }

    /**
     * A dual-mode device holds a hardware credential after an ADMS (panel) enrolment, so the
     * template must be labelled `device_native`. Labelling it with the device's cloud
     * `provider_type` routed the erasure to a provider that never held the credential: the
     * terminal kept it while the app reported the member revoked.
     */
    #[Test]
    public function a_dual_mode_adms_enrolment_is_labelled_hardware_and_erased_through_the_terminal(): void
    {
        $dualMode = BiometricDevice::factory()->dualMode('SN-DUAL')->create([
            'gym_id' => $this->gym->id,
            'device_type' => 'fingerprint',
        ]);

        $this->actingAs($this->gymOwner());

        EnrollBiometricAction::handle($this->member, [
            'device_id' => $dualMode->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint->value,
        ]);

        $template = BiometricTemplate::query()
            ->where('gym_member_id', $this->member->id)
            ->where('enrolled_by_device_id', $dualMode->id)
            ->firstOrFail();

        $this->assertSame('device_native', $template->provider_type);
        $this->assertFalse($template->hasCloudProvider());

        // The operator completed the enrolment at the terminal.
        $template->update(['hardware_confirmed_at' => now()]);

        resolve(MemberArchiveService::class)->archiveAsSystem($this->member->fresh());

        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->sync_status);
        $this->assertDatabaseHas('adms_device_commands', [
            'device_id' => $dualMode->id,
            'command_type' => 'user_delete',
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);
    }

    /**
     * A device-reported sync must never change the provenance of a row it did not author.
     *
     * On a dual-mode device `enroll()` (`:68`) creates an active cloud row. The `enroll-sync`
     * branch used to select that row on member + modality alone and overwrite its
     * `provider_type` and `template_ref` in place, orphaning the provider credential: the app
     * then routed the erasure to the terminal and reported the member revoked while the
     * provider still held a live template.
     */
    #[Test]
    public function a_device_reported_sync_does_not_relabel_an_active_cloud_row(): void
    {
        $dualMode = BiometricDevice::factory()->dualMode('SN-DUAL-SYNC')
            ->withToken($token = 'dual-sync-token-123456')
            ->create([
                'gym_id' => $this->gym->id,
                'device_type' => 'fingerprint',
            ]);

        // The shape `BiometricEnrollController::enroll()` writes for a dual-mode device's
        // cloud-backed enrolment: the device's cloud provider, active, and no Pending
        // placeholder (a cloud enrol never queues an ADMS user_add).
        $cloud = BiometricTemplate::factory()->cloudBacked('rekognition')->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $dualMode->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'template_ref' => '{"provider":"rekognition","externalId":"face-xyz","meta":[]}',
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/biometric/enroll-sync', [
                'gym_member_id' => $this->member->id,
                'biometric_type' => BiometricTypeEnum::Fingerprint->value,
                'template_ref' => (string) $this->member->id,
                'quality_score' => 0.9,
            ])
            ->assertOk();

        $cloud->refresh();
        $this->assertSame('rekognition', $cloud->provider_type, 'A device sync must not change a row it did not author.');
        $this->assertTrue($cloud->hasCloudProvider());
        $this->assertSame(
            '{"provider":"rekognition","externalId":"face-xyz","meta":[]}',
            $cloud->template_ref,
            'The cloud row must keep its own provider reference.'
        );
        $this->assertSame(
            BiometricTemplateSyncStatusEnum::Synced,
            $cloud->sync_status,
            'The cloud row stays live so its own provider-side erase is still driven.'
        );

        // The terminal credential the device just reported is tracked as its own row, so the
        // door cannot honour a credential the app does not know about.
        $hardware = BiometricTemplate::query()
            ->where('gym_member_id', $this->member->id)
            ->where('enrolled_by_device_id', $dualMode->id)
            ->where('biometric_type', BiometricTypeEnum::Fingerprint)
            ->where('id', '!=', $cloud->id)
            ->sole();

        $this->assertSame('device_native', $hardware->provider_type);

        resolve(MemberArchiveService::class)->archiveAsSystem($this->member->fresh());

        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $cloud->refresh()->sync_status);
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $hardware->refresh()->sync_status);

        $this->assertDatabaseHas('adms_device_commands', [
            'device_id' => $dualMode->id,
            'command_type' => 'user_delete',
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);
    }

    /**
     * Archiving must revoke every live credential — and `Model::preventLazyLoading()` only
     * enforces the violation for result sets larger than one, so a member holding a single
     * template hid the fact that `revokeBiometricCredentials()` never eager-loaded the device
     * the de-provisioner resolves. With two templates, every erase threw
     * `LazyLoadingViolationException`, was swallowed per template, and left both credentials
     * live while the app reported the member archived.
     */
    #[Test]
    public function archiving_revokes_every_template_when_the_member_holds_more_than_one(): void
    {
        $fingerprint = BiometricTemplate::factory()->deviceNative()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
            'hardware_confirmed_at' => now(),
        ]);

        $face = BiometricTemplate::factory()->deviceNative()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->deviceB->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
            'hardware_confirmed_at' => now(),
        ]);

        resolve(MemberArchiveService::class)->archiveAsSystem($this->member->fresh());

        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $fingerprint->refresh()->sync_status);
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $face->refresh()->sync_status);

        $this->assertSame(
            2,
            AdmsDeviceCommand::query()->where('command_type', 'user_delete')->count(),
            'Each device holding a credential must get its own PIN-scoped delete.'
        );
    }

    /**
     * An obligation marker is a machine-written erasure record, not an enrolment a fan-out
     * replaces. The sibling upsert must not activate it or overwrite its reference — that
     * would destroy the only trace of an un-erased credential.
     *
     * The fixture isolates the flag guard: it is only the `is_erasure_obligation` filter that
     * excludes it, because its `Pending` state is not one the status list excludes.
     */
    #[Test]
    public function a_sibling_erasure_obligation_marker_is_not_repurposed_by_a_fan_out(): void
    {
        $marker = BiometricTemplate::factory()->erasureObligation()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->deviceB->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'provider_type' => 'device_native',
            'template_ref' => null,
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
            'is_active' => true,
        ]);

        $origin = BiometricTemplate::factory()->synced()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
        ]);

        resolve(BiometricTemplatePropagator::class)
            ->propagateToSiblingDevices($this->deviceA, $this->member, $origin);

        $marker->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Pending, $marker->sync_status);
        $this->assertTrue((bool) $marker->is_active);
        $this->assertTrue((bool) $marker->is_erasure_obligation);
        $this->assertNull($marker->template_ref, 'The obligation marker must keep its own (absent) reference.');

        $sibling = BiometricTemplate::query()
            ->where('gym_member_id', $this->member->id)
            ->where('enrolled_by_device_id', $this->deviceB->id)
            ->where('biometric_type', BiometricTypeEnum::Face)
            ->where('id', '!=', $marker->id)
            ->sole();

        $this->assertSame(BiometricTemplateSyncStatusEnum::Propagated, $sibling->sync_status);
        $this->assertFalse((bool) $sibling->is_erasure_obligation);
    }

    /**
     * The second half of the same guard, isolated: a `DeletionUnresolvable` row is excluded by
     * the status list alone (its flag is false), so dropping the status filter must be
     * observable.
     */
    #[Test]
    public function a_deletion_unresolvable_sibling_is_not_repurposed_by_a_fan_out(): void
    {
        $unresolvable = BiometricTemplate::factory()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->deviceB->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'provider_type' => 'device_native',
            'template_ref' => 'orphan-ref',
            'sync_status' => BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            'is_active' => false,
            'is_erasure_obligation' => false,
        ]);

        $origin = BiometricTemplate::factory()->synced()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
        ]);

        resolve(BiometricTemplatePropagator::class)
            ->propagateToSiblingDevices($this->deviceA, $this->member, $origin);

        $unresolvable->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable, $unresolvable->sync_status);
        $this->assertFalse((bool) $unresolvable->is_active);
        $this->assertSame('orphan-ref', $unresolvable->template_ref);

        $sibling = BiometricTemplate::query()
            ->where('gym_member_id', $this->member->id)
            ->where('enrolled_by_device_id', $this->deviceB->id)
            ->where('biometric_type', BiometricTypeEnum::Face)
            ->where('id', '!=', $unresolvable->id)
            ->sole();

        $this->assertSame(BiometricTemplateSyncStatusEnum::Propagated, $sibling->sync_status);
    }

    /**
     * A terminal ACK only proves anything about credentials the terminal holds. A cloud-backed
     * row on the same device shares the PIN but not the credential and must not be converged.
     */
    #[Test]
    public function a_cloud_row_is_not_converged_by_a_hardware_ack(): void
    {
        $cloud = BiometricTemplate::factory()->cloudBacked('rekognition')->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        $hardware = BiometricTemplate::factory()->deviceNative()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'idempotency_key' => AdmsDeviceCommand::deleteIdempotencyKeyFor($this->deviceA->id, (string) $this->member->id),
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 1,
        ]);

        $this->assertTrue($command->markAcknowledged('0'));

        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $cloud->refresh()->sync_status);
        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $hardware->refresh()->sync_status);
    }

    /**
     * A listed "already absent" return code converges a `user_delete`; an unlisted one is a
     * failure. The default ships empty, so a wrong entry cannot silently fabricate an erasure.
     */
    #[Test]
    public function deletion_convergence_codes_are_two_sided_and_default_to_empty(): void
    {
        // The shipped default is asserted BEFORE any override: it is the one setting whose
        // wrong value fabricates an erasure, so a silent revert to a permissive list must
        // turn this suite red.
        $this->assertSame(
            [],
            config('biometrics.adms.deletion_convergence_codes'),
            'The shipped default must be empty: an unlisted "absent" code must never converge.'
        );

        $strict = $this->sentDeleteCommand();
        $strict->markAcknowledged('not_found');
        $this->assertSame(
            AdmsCommandStatusEnum::FAILED,
            $strict->refresh()->status,
            'With no configured convergence codes a non-zero code must be a failure.'
        );

        config()->set('biometrics.adms.deletion_convergence_codes', ['not_found']);

        $configured = $this->sentDeleteCommand();
        $configured->markAcknowledged('not_found');
        $this->assertSame(AdmsCommandStatusEnum::ACKNOWLEDGED, $configured->refresh()->status);

        $other = $this->sentDeleteCommand();
        $other->markAcknowledged('ERROR: rejected');
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $other->refresh()->status);
    }

    /**
     * The full renewal cycle: an archived, erased PIN is re-enrolled and archived again. The
     * previous ACKNOWLEDGED `user_delete` still holds the UNIQUE key, so the renewed obligation
     * must re-drive it in place rather than be counted as a duplicate.
     */
    #[Test]
    public function a_re_archived_re_enrolled_pin_re_drives_the_existing_delete_exactly_once(): void
    {
        $first = $this->deliveredTemplate();

        resolve(MemberArchiveService::class)->archiveAsSystem($this->member->fresh());

        $command = AdmsDeviceCommand::query()->where('command_type', 'user_delete')->sole();
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $command->status);

        $this->assertTrue($command->markSent());
        $this->assertTrue($command->markAcknowledged('0'));
        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $first->refresh()->sync_status);

        // Restore, re-enrol (a fresh hardware credential on the same PIN), archive again.
        resolve(MemberArchiveService::class)->unarchiveAsSystem($this->member->fresh());

        $this->deliveredTemplate();

        resolve(MemberArchiveService::class)->archiveAsSystem($this->member->fresh());

        $commands = AdmsDeviceCommand::query()->where('command_type', 'user_delete')->get();
        $this->assertCount(1, $commands, 'The renewed obligation must reuse the terminal row, never insert a duplicate.');
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $commands->first()->status);
        $this->assertSame(0, $commands->first()->attempt_count);
    }

    /**
     * Negative control: an ordinary single archive queues exactly one delete and no spurious
     * re-drive.
     */
    #[Test]
    public function an_ordinary_single_archive_queues_exactly_one_delete(): void
    {
        $this->deliveredTemplate();

        resolve(MemberArchiveService::class)->archiveAsSystem($this->member->fresh());

        $this->assertSame(1, AdmsDeviceCommand::query()->where('command_type', 'user_delete')->count());

        $this->artisan('biometrics:reconcile-pending-deletions')->assertSuccessful();

        // The command is already live, so the reconciler must not duplicate or re-drive it.
        $commands = AdmsDeviceCommand::query()->where('command_type', 'user_delete')->get();
        $this->assertCount(1, $commands);
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $commands->first()->status);
    }

    /**
     * A budget-exhausted cloud erasure is a residual, not an error: the member's own erasure
     * (the anonymise step and the consent deletion) must still complete.
     *
     * The counter is derived through the real path — two provider failures separated by more
     * than the cooldown — rather than hand-written. The bound is stamped once, by the first
     * failure, so the second failure is the one that exhausts the budget.
     */
    #[Test]
    public function a_budget_exhausted_cloud_erasure_does_not_block_the_members_erasure(): void
    {
        config()->set('biometrics.cloud_erasure_cooldown_hours', 1);

        $template = BiometricTemplate::factory()->cloudBacked('unconfigured_cloud_provider')->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => null,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => '{"provider":"unconfigured_cloud_provider","externalId":"face-1","meta":[]}',
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        $deProvisioner = resolve(BiometricDeProvisioner::class);

        $this->assertSame(BiometricDeProvisionOutcome::CloudRetry, $deProvisioner->deprovision($template));

        $template->refresh();
        $this->assertSame(1, $template->erasure_attempts);
        $this->assertNotNull($template->erasure_first_attempted_at);

        $this->travel(2)->hours();

        $this->assertSame(
            BiometricDeProvisionOutcome::CloudUnresolvable,
            $deProvisioner->deprovision($template->refresh())
        );

        $template->refresh();
        $this->assertSame(2, $template->erasure_attempts);
        $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable, $template->sync_status);

        resolve(BiometricDataDeletionService::class)->deleteForGymMember($this->member->fresh());

        $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable, $template->refresh()->sync_status);
        $this->assertDatabaseMissing('biometric_consents', ['gym_member_id' => $this->member->id]);
    }

    /**
     * The budget bound is monotonic in the database. A stale model instance that still sees
     * `erasure_first_attempted_at` as null must not overwrite the first writer's stamp and move
     * the clock forward — that is how a stale writer used to undo an operator's "Retry Erasure"
     * reset.
     */
    #[Test]
    public function a_stale_writer_cannot_move_the_cloud_erasure_bound_forward(): void
    {
        $template = BiometricTemplate::factory()->cloudBacked('unconfigured_cloud_provider')->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => null,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => '{"provider":"unconfigured_cloud_provider","externalId":"face-stale","meta":[]}',
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        $stale = BiometricTemplate::query()->findOrFail($template->id);

        $deProvisioner = resolve(BiometricDeProvisioner::class);

        $this->assertSame(BiometricDeProvisionOutcome::CloudRetry, $deProvisioner->deprovision($template));

        $firstAttemptedAt = $template->refresh()->erasure_first_attempted_at;
        $this->assertNotNull($firstAttemptedAt);

        $this->travel(1)->hours();

        $this->assertSame(BiometricDeProvisionOutcome::CloudRetry, $deProvisioner->deprovision($stale));

        $template->refresh();
        $this->assertNotNull($template->erasure_first_attempted_at);
        $this->assertSame(
            $firstAttemptedAt->toDateTimeString(),
            $template->erasure_first_attempted_at->toDateTimeString(),
            'Only the first writer may stamp the bound; a stale null must not move it forward.'
        );
        $this->assertSame(2, $template->erasure_attempts);
    }

    /**
     * An operator's "Retry Erasure" reset nulls the bound while an in-flight instance still
     * holds the pre-reset stamp. The first failure after the reset must measure from the
     * freshly stamped bound, not the stale model's value: the old read-modify-write wrote the
     * pre-reset stamp back and computed `$budgetExhausted` from it, so the first failure after
     * a reset immediately re-terminalised the row.
     */
    #[Test]
    public function a_stale_pre_reset_bound_cannot_immediately_exhaust_the_cloud_budget_again(): void
    {
        config()->set('biometrics.cloud_erasure_cooldown_hours', 1);

        $template = BiometricTemplate::factory()->cloudBacked('unconfigured_cloud_provider')->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => null,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => '{"provider":"unconfigured_cloud_provider","externalId":"face-reset","meta":[]}',
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        $deProvisioner = resolve(BiometricDeProvisioner::class);

        // The first failure stamps the bound at t0.
        $this->assertSame(BiometricDeProvisionOutcome::CloudRetry, $deProvisioner->deprovision($template));

        // A writer that still holds t0 and will not observe the operator's reset.
        $stale = BiometricTemplate::query()->findOrFail($template->id);
        $preResetBound = $stale->erasure_first_attempted_at;
        $this->assertNotNull($preResetBound);

        $this->travel(2)->hours();

        // The operator's "Retry Erasure" reset, written by a different instance.
        $reset = BiometricTemplate::query()->findOrFail($template->id);
        $reset->forceFill([
            'erasure_first_attempted_at' => null,
            'erasure_attempts' => 0,
        ])->save();

        $this->assertSame(
            BiometricDeProvisionOutcome::CloudRetry,
            $deProvisioner->deprovision($stale),
            'The first failure after a reset must not re-terminalise off the pre-reset stamp.'
        );

        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->sync_status);
        $this->assertNotNull($template->erasure_first_attempted_at);
        $this->assertSame(
            now()->toDateTimeString(),
            $template->erasure_first_attempted_at->toDateTimeString(),
            'The bound must be the fresh stamp taken under the guard, not the pre-reset value.'
        );
        $this->assertSame(1, $template->erasure_attempts);
    }

    /**
     * A transient provider failure is still retryable and must propagate so the listener retries
     * — and must not anonymise anything yet.
     *
     * Three fast retries are exactly the consent-revoked listener's whole budget (60/180/300s
     * backoff). Under the old retry *count* those three calls were enough to drive the row
     * terminal; measured against the cooldown they must all stay retryable.
     */
    #[Test]
    public function a_transient_cloud_failure_still_propagates_without_anonymising(): void
    {
        $template = BiometricTemplate::factory()->cloudBacked('unconfigured_cloud_provider')->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => null,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => '{"provider":"unconfigured_cloud_provider","externalId":"face-2","meta":[]}',
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        foreach (range(1, 3) as $attempt) {
            $failure = null;

            try {
                resolve(BiometricDataDeletionService::class)->deleteForGymMember($this->member->fresh());
            } catch (RuntimeException $e) {
                $failure = $e;
            }

            $this->assertInstanceOf(
                RuntimeException::class,
                $failure,
                'A retryable cloud failure must propagate for the listener to retry.'
            );
            $this->assertStringContainsString('Biometric data deletion failed', $failure->getMessage());

            $this->assertSame(
                BiometricTemplateSyncStatusEnum::PendingDeletion,
                $template->refresh()->sync_status,
                "Retry {$attempt} must stay retryable: the budget is time, not a retry count."
            );
        }

        $this->assertSame(3, $template->erasure_attempts);
        $this->assertDatabaseHas('biometric_consents', ['gym_member_id' => $this->member->id]);
    }

    /**
     * The queue's retry bound must not become an erasure bound. When the listener has spent its
     * `$tries` while the provider is still failing, the member's own erasure (anonymise +
     * consent deletion) still completes and the obligation is left to the hourly reconciler.
     */
    #[Test]
    public function an_exhausted_queue_still_completes_the_members_erasure(): void
    {
        $template = BiometricTemplate::factory()->cloudBacked('unconfigured_cloud_provider')->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => null,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => '{"provider":"unconfigured_cloud_provider","externalId":"face-3","meta":[]}',
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        resolve(BiometricDataDeletionService::class)
            ->deleteForGymMember($this->member->fresh(), finalAttempt: true);

        // The obligation is still outstanding and still retryable for the reconciler...
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->refresh()->sync_status);
        $this->assertNotNull($template->erasure_first_attempted_at);

        // ...but the member's own erasure is not abandoned with the job.
        $this->assertDatabaseMissing('biometric_consents', ['gym_member_id' => $this->member->id]);
    }

    #[Test]
    public function the_template_factory_provider_is_deterministic(): void
    {
        $first = BiometricTemplate::factory()->create();
        $second = BiometricTemplate::factory()->create();

        $this->assertSame($first->provider_type, $second->provider_type);
        $this->assertTrue($first->hasCloudProvider());

        $native = BiometricTemplate::factory()->deviceNative()->create();
        $this->assertSame('device_native', $native->provider_type);
        $this->assertFalse($native->hasCloudProvider());

        $cloud = BiometricTemplate::factory()->cloudBacked('rekognition')->create();
        $this->assertSame('rekognition', $cloud->provider_type);
    }

    /**
     * The enum owns the live/erasable state sets so the archive service, the sweep and the
     * reconciler cannot drift.
     */
    #[Test]
    public function the_sync_status_enum_owns_the_live_credential_state_set(): void
    {
        $live = BiometricTemplateSyncStatusEnum::liveOrPendingCredentialStates();

        $this->assertContains(BiometricTemplateSyncStatusEnum::Pending, $live);
        $this->assertContains(BiometricTemplateSyncStatusEnum::Propagated, $live);
        $this->assertContains(BiometricTemplateSyncStatusEnum::Synced, $live);
        $this->assertContains(BiometricTemplateSyncStatusEnum::PendingDeletion, $live);
        $this->assertNotContains(BiometricTemplateSyncStatusEnum::Deleted, $live);
        $this->assertNotContains(BiometricTemplateSyncStatusEnum::Superseded, $live);
        $this->assertNotContains(BiometricTemplateSyncStatusEnum::DeletionUnresolvable, $live);

        $liveOnly = BiometricTemplateSyncStatusEnum::liveCredentialStates();
        $this->assertNotContains(
            BiometricTemplateSyncStatusEnum::PendingDeletion,
            $liveOnly,
            'Completing an outstanding erasure is the reconciler\'s job, not a sweep\'s.'
        );

        foreach (BiometricTemplateSyncStatusEnum::cases() as $status) {
            $this->assertSame(
                in_array($status, $live, true),
                $status->representsLiveOrPendingCredential()
            );
        }
    }

    /**
     * The other half of the epoch rule: a delete that exhausted its delivery budget is re-driven
     * once the PIN has been re-enrolled, because a `user_add` the terminal accepted *after* the
     * delete last failed proves a fresh credential exists — on the same PIN, so the same
     * PIN-scoped delete key. Deciding "same obligation vs new epoch" after the exhausted
     * short-circuit left that credential live with no automated path.
     *
     * The acknowledgement is consumed by the re-drive that credits it: when the re-driven
     * delete exhausts again with no further re-enrolment, the cap must survive instead of
     * resetting on that same `user_add`.
     */
    #[Test]
    public function a_credential_re_enrolled_after_an_exhausted_delete_is_re_driven(): void
    {
        $this->deliveredTemplate();

        resolve(MemberArchiveService::class)->archiveAsSystem($this->member->fresh());

        $command = AdmsDeviceCommand::query()->where('command_type', 'user_delete')->sole();
        $pin = (string) $this->member->id;

        $this->driveDeleteToExhaustion($command, AdmsCommandKey::maxAttempts());

        // The row is old, as it would be in production. The epoch anchor is the delete's last
        // failure plus the consumed `user_add` id, not `created_at`: the row is re-driven in
        // place and `created_at` never advances.
        $command->refresh()->forceFill(['created_at' => now()->subDay()])->save();

        $this->assertFalse(
            resolve(BiometricDeProvisioner::class)->hasUnconsumedHardwareCredential($this->deviceA->id, $pin, $this->gym->id),
            'Without a fresh credential the exhausted delete has no epoch to start.'
        );

        // Same obligation, no new credential: still exhausted.
        resolve(MemberArchiveService::class)->unarchiveAsSystem($this->member->fresh());
        resolve(MemberArchiveService::class)->archiveAsSystem($this->member->fresh());

        $this->assertSame(
            AdmsCommandStatusEnum::FAILED,
            $command->refresh()->status,
            'Without a fresh credential the exhausted delete is the same obligation.'
        );

        // Re-enrolment happens on a later wall-clock tick than the failure it replaces: the
        // first epoch is still timestamp-anchored, and a test that runs inside one second must
        // not rely on a second-precision tie.
        $this->travel(1)->minutes();

        // Restore, re-enrol a fresh hardware credential on the same PIN, archive again.
        resolve(MemberArchiveService::class)->unarchiveAsSystem($this->member->fresh());
        $this->deliveredTemplate();
        $freshUserAdd = $this->acceptedUserAdd();

        $this->assertTrue(
            resolve(BiometricDeProvisioner::class)->hasUnconsumedHardwareCredential($this->deviceA->id, $pin, $this->gym->id),
            'A credential accepted after the last failure is a fresh obligation.'
        );

        resolve(MemberArchiveService::class)->archiveAsSystem($this->member->fresh());

        $command->refresh();
        $this->assertSame(
            AdmsCommandStatusEnum::PENDING,
            $command->status,
            'A credential re-enrolled after the exhausted delete is a new obligation.'
        );
        $this->assertSame(0, $command->attempt_count);
        $this->assertSame(
            $freshUserAdd->id,
            $command->epoch_credential_user_add_id,
            'The re-drive must consume the acknowledgement that started the epoch.'
        );

        // The re-driven delete exhausts a second time. The consumed acknowledgement must not
        // start another epoch, so the cap — and the dead-letter signal — are durable.
        $this->driveDeleteToExhaustion($command, AdmsCommandKey::maxAttempts());

        $command->refresh();
        $this->assertSame(AdmsCommandKey::maxAttempts(), $command->attempt_count);
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $command->status);
        $this->assertFalse(
            resolve(BiometricDeProvisioner::class)->hasUnconsumedHardwareCredential($this->deviceA->id, $pin, $this->gym->id),
            'The same user_add must not start another epoch once consumed.'
        );

        resolve(MemberArchiveService::class)->unarchiveAsSystem($this->member->fresh());
        resolve(MemberArchiveService::class)->archiveAsSystem($this->member->fresh());

        $command->refresh();
        $this->assertSame(
            AdmsCommandStatusEnum::FAILED,
            $command->status,
            'A consumed acknowledgement must not re-drive the exhausted delete again.'
        );
        $this->assertSame(AdmsCommandKey::maxAttempts(), $command->attempt_count);

        // A genuinely newer credential does start another epoch and re-drives the delete.
        resolve(MemberArchiveService::class)->unarchiveAsSystem($this->member->fresh());
        $newerUserAdd = $this->acceptedUserAdd(stage: AdmsCommandKey::STAGE_RECONCILE);

        $this->assertTrue(
            resolve(BiometricDeProvisioner::class)->hasUnconsumedHardwareCredential($this->deviceA->id, $pin, $this->gym->id)
        );

        resolve(MemberArchiveService::class)->archiveAsSystem($this->member->fresh());

        $command->refresh();
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $command->status);
        $this->assertSame(0, $command->attempt_count);
        $this->assertSame($newerUserAdd->id, $command->epoch_credential_user_add_id);
    }

    /**
     * Negative control: a credential that predates the delete cannot be a fresh obligation,
     * even though it is the newest `user_add` in the log.
     *
     * The old anchor was the delete's `created_at`; the row is re-driven in place, so a stale
     * acknowledgement satisfied "newer" for the row's whole life. The anchor is now the last
     * delivery outcome (and, on later cycles, the consumed marker), so a credential that
     * predates the delete's creation — acknowledged, or merely dequeued — must not re-drive a
     * `FAILED`-at-cap delete.
     */
    #[Test]
    public function a_stale_user_add_does_not_re_drive_an_exhausted_delete(): void
    {
        foreach ([AdmsCommandStatusEnum::ACKNOWLEDGED, AdmsCommandStatusEnum::SENT] as $status) {
            $pin = $status === AdmsCommandStatusEnum::ACKNOWLEDGED ? '880001' : '880002';

            $userAdd = $status === AdmsCommandStatusEnum::ACKNOWLEDGED
                ? $this->acceptedUserAdd($pin)
                : $this->sentUserAdd($pin);

            $userAdd->forceFill([
                'created_at' => now()->subDays(2),
                'sent_at' => now()->subDays(2),
                'acknowledged_at' => $status === AdmsCommandStatusEnum::ACKNOWLEDGED ? now()->subDays(2) : null,
            ])->save();

            $delete = AdmsDeviceCommand::query()->create([
                'gym_id' => $this->gym->id,
                'device_id' => $this->deviceA->id,
                'command_type' => 'user_delete',
                'command_payload' => ['Pin' => $pin],
                'idempotency_key' => AdmsDeviceCommand::deleteIdempotencyKeyFor($this->deviceA->id, $pin),
                'status' => AdmsCommandStatusEnum::FAILED,
                'attempt_count' => AdmsCommandKey::maxAttempts(),
                'sent_at' => now()->subDay(),
                'acknowledged_at' => now()->subHours(2),
            ]);

            $delete->forceFill(['created_at' => now()->subDay()])->save();

            $template = BiometricTemplate::factory()->deviceNative()->create([
                'gym_member_id' => $this->member->id,
                'gym_id' => $this->gym->id,
                'enrolled_by_device_id' => $this->deviceA->id,
                'biometric_type' => BiometricTypeEnum::Fingerprint,
                'template_ref' => $pin,
                'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
                'is_active' => false,
            ]);

            $this->assertSame(
                BiometricDeProvisionOutcome::Exhausted,
                resolve(BiometricDeProvisioner::class)->deprovision($template),
                "A stale {$status->value} user_add must not start a new epoch."
            );

            $delete->refresh();
            $this->assertSame(AdmsCommandStatusEnum::FAILED, $delete->status);
            $this->assertSame(AdmsCommandKey::maxAttempts(), $delete->attempt_count);
        }
    }

    /**
     * The first-epoch anchor is the delete's last delivery OUTCOME, not its creation.
     *
     * The fixture places the credential BETWEEN the two candidate anchors — newer than the
     * delete's forced `created_at` (-1 day) but older than its last rejection (-2 hours) — so
     * reverting the anchor to `created_at` flips the outcome from `Exhausted` to `ReDriven`.
     * The dedicated stale-user_add negative control places its credential older than both
     * anchors and cannot pin the precedence itself.
     */
    #[Test]
    public function the_first_epoch_anchor_is_the_deletes_last_delivery_outcome(): void
    {
        $pin = '880003';

        $stale = $this->acceptedUserAdd($pin);

        $stale->forceFill([
            'created_at' => now()->subHours(6),
            'sent_at' => now()->subHours(6),
            'acknowledged_at' => now()->subHours(6),
        ])->save();

        $delete = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => $pin],
            'idempotency_key' => AdmsDeviceCommand::deleteIdempotencyKeyFor($this->deviceA->id, $pin),
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => AdmsCommandKey::maxAttempts(),
            'sent_at' => now()->subDay(),
            'acknowledged_at' => now()->subHours(2),
        ]);

        $delete->forceFill(['created_at' => now()->subDay()])->save();

        $template = BiometricTemplate::factory()->deviceNative()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'template_ref' => $pin,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        $deProvisioner = resolve(BiometricDeProvisioner::class);

        $this->assertSame(
            BiometricDeProvisionOutcome::Exhausted,
            $deProvisioner->deprovision($template),
            'A credential older than the delete\'s last rejection is the same obligation.'
        );

        $delete->refresh();
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $delete->status);
        $this->assertSame(AdmsCommandKey::maxAttempts(), $delete->attempt_count);
        $this->assertNull($delete->epoch_credential_user_add_id);

        // Twin: a credential accepted after the last rejection does start the epoch.
        $newer = $this->acceptedUserAdd($pin, null, AdmsCommandKey::STAGE_RECONCILE);

        $this->assertSame(
            BiometricDeProvisionOutcome::ReDriven,
            $deProvisioner->deprovision($template->refresh())
        );

        $delete->refresh();
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $delete->status);
        $this->assertSame(0, $delete->attempt_count);
        $this->assertSame($newer->id, $delete->epoch_credential_user_add_id);
    }

    /**
     * The consumed marker is exactly-once: once the delete credits a `user_add`, that
     * acknowledgement cannot start a second epoch, while a strictly newer one can. Ids are
     * monotonic, so a timestamp tie cannot hide the newer credential.
     */
    #[Test]
    public function a_consumed_epoch_credential_cannot_start_a_second_epoch(): void
    {
        $pin = (string) $this->member->id;
        $consumed = $this->acceptedUserAdd();

        $delete = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => $pin],
            'idempotency_key' => AdmsDeviceCommand::deleteIdempotencyKeyFor($this->deviceA->id, $pin),
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => AdmsCommandKey::maxAttempts(),
            'sent_at' => now()->subDay(),
            'acknowledged_at' => now()->subDay(),
        ]);

        // The fixture's delivery timestamps predate its creation (an ordering production
        // cannot produce), so pull `created_at` back to keep the fixture reachable. The anchor
        // branch is unused here: the consumed id is set.
        $delete->forceFill([
            'epoch_credential_user_add_id' => $consumed->id,
            'created_at' => now()->subDays(2),
        ])->save();

        $template = BiometricTemplate::factory()->deviceNative()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'template_ref' => $pin,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        $deProvisioner = resolve(BiometricDeProvisioner::class);

        $this->assertFalse(
            $deProvisioner->hasUnconsumedHardwareCredential($this->deviceA->id, $pin, $this->gym->id),
            'The consumed user_add must not start another epoch.'
        );
        $this->assertSame(BiometricDeProvisionOutcome::Exhausted, $deProvisioner->deprovision($template));

        $delete->refresh();
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $delete->status);
        $this->assertSame(AdmsCommandKey::maxAttempts(), $delete->attempt_count);

        $newer = $this->acceptedUserAdd(stage: AdmsCommandKey::STAGE_RECONCILE);

        $this->assertTrue($deProvisioner->hasUnconsumedHardwareCredential($this->deviceA->id, $pin, $this->gym->id));
        $this->assertSame(BiometricDeProvisionOutcome::ReDriven, $deProvisioner->deprovision($template));

        $delete->refresh();
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $delete->status);
        $this->assertSame(0, $delete->attempt_count);
        $this->assertSame($newer->id, $delete->epoch_credential_user_add_id);
    }

    /**
     * A `SENT`-only `user_add` counts as a credential the terminal may already hold — the same
     * rule `wasDeliveredToHardware()` uses — so it is an unconsumed epoch until the delete
     * credits it. This drives the command through its real lifecycle rather than hand-writing
     * an accepted row.
     */
    #[Test]
    public function a_sent_only_user_add_counts_as_an_unconsumed_epoch_credential(): void
    {
        $pin = (string) $this->member->id;

        $delete = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => $pin],
            'idempotency_key' => AdmsDeviceCommand::deleteIdempotencyKeyFor($this->deviceA->id, $pin),
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => 1,
            'sent_at' => now()->subDay(),
            'acknowledged_at' => now()->subDay(),
        ]);

        $deProvisioner = resolve(BiometricDeProvisioner::class);

        $this->assertFalse($deProvisioner->hasUnconsumedHardwareCredential($this->deviceA->id, $pin, $this->gym->id));

        $sent = $this->sentUserAdd();

        $this->assertTrue(
            $deProvisioner->hasUnconsumedHardwareCredential($this->deviceA->id, $pin, $this->gym->id),
            'A SENT user_add may already be on the terminal even without an acknowledgement.'
        );

        $delete->forceFill(['epoch_credential_user_add_id' => $sent->id])->save();

        $this->assertFalse($deProvisioner->hasUnconsumedHardwareCredential($this->deviceA->id, $pin, $this->gym->id));
    }

    /**
     * The device relation must be loaded by the de-provisioner itself, not by whichever caller
     * happened to eager-load it. `Model::preventLazyLoading()` only enforces the violation for
     * result sets larger than one, so a multi-row collection with no eager load is the shape
     * that proves `deprovision()`'s `loadMissing('enrolledByDevice')` is the mechanism.
     */
    #[Test]
    public function deprovisioning_a_multi_row_collection_without_eager_loading_parks_every_credential(): void
    {
        $fingerprint = BiometricTemplate::factory()->deviceNative()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
            'hardware_confirmed_at' => now(),
        ]);

        $face = BiometricTemplate::factory()->deviceNative()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->deviceB->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
            'hardware_confirmed_at' => now(),
        ]);

        $templates = BiometricTemplate::query()
            ->where('gym_member_id', $this->member->id)
            ->get();

        $this->assertCount(2, $templates);

        $first = $templates->first();
        $second = $templates->last();

        $this->assertInstanceOf(BiometricTemplate::class, $first);
        $this->assertInstanceOf(BiometricTemplate::class, $second);
        $this->assertNotSame($first->id, $second->id);
        $this->assertFalse($first->relationLoaded('enrolledByDevice'));
        $this->assertFalse($second->relationLoaded('enrolledByDevice'));

        $this->assertTrue(resolve(BiometricDataDeletionService::class)->deleteSingleTemplate($first));
        $this->assertSame(
            BiometricDeProvisionOutcome::Queued,
            resolve(BiometricDeProvisioner::class)->deprovision($second)
        );

        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $fingerprint->refresh()->sync_status);
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $face->refresh()->sync_status);

        $this->assertSame(
            2,
            AdmsDeviceCommand::query()->where('command_type', 'user_delete')->count(),
            'Each device holding a credential must get its own PIN-scoped delete.'
        );
    }

    /**
     * An already-terminal `DeletionUnresolvable` row is the operator's to resolve: an erasure
     * request must not fabricate a request marker, re-open the row, or re-log the transition.
     * It is reported as a residual on the activity line instead.
     */
    #[Test]
    public function an_already_unresolvable_row_is_left_unchanged_by_the_members_erasure(): void
    {
        $template = BiometricTemplate::factory()->cloudBacked('unconfigured_cloud_provider')->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => null,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => null,
            'sync_status' => BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            'is_active' => false,
        ]);

        resolve(BiometricDataDeletionService::class)
            ->deleteForGymMember($this->member->fresh(), finalAttempt: true);

        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable, $template->sync_status);
        $this->assertNull(
            $template->erasure_requested_at,
            'The entry state must not fabricate an erasure request marker.'
        );
        $this->assertDatabaseMissing('biometric_consents', ['gym_member_id' => $this->member->id]);

        $activity = DB::table('activity_log')
            ->where('log_name', 'biometric')
            ->where('subject_id', $this->member->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'Expected a biometric activity log entry for the erasure');
        $this->assertStringContainsString('unresolvable', (string) $activity->description);

        $properties = json_decode((string) $activity->properties, true);
        $this->assertCount(1, $properties['unresolvable_erasures']);
        $this->assertSame($template->id, $properties['unresolvable_erasures'][0]['template_id']);
    }

    /**
     * An unreadable encrypted `template_ref` (a rotated `APP_KEY`, a corrupted row) must not
     * abort the whole erasure. The skip predicate reads the reference before the per-template
     * try/catch, so a `DecryptException` there used to escape `deleteForGymMember()` entirely:
     * the remaining templates were skipped and the member's own anonymise/consent step never
     * ran. The unreadable row is now reported as unresolvable — distinguished from the
     * blank-reference arm by its `error_type` — and the loop continues.
     */
    #[Test]
    public function an_unreadable_template_reference_does_not_abort_the_members_erasure(): void
    {
        $unreadable = BiometricTemplate::factory()->cloudBacked('unconfigured_cloud_provider')->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => null,
            'biometric_type' => BiometricTypeEnum::Face,
            'sync_status' => BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            'is_active' => false,
        ]);

        // Written past the model: this is what an undecryptable payload (a rotated key, a
        // corrupted row) looks like on read.
        DB::table('biometric_templates')
            ->where('id', $unreadable->id)
            ->update(['template_ref' => 'not-a-valid-encrypted-payload']);

        $healthy = $this->deliveredTemplate();

        resolve(BiometricDataDeletionService::class)
            ->deleteForGymMember($this->member->fresh());

        // The member's own erasure completed: consent is gone even though one row could not
        // even be read.
        $this->assertDatabaseMissing('biometric_consents', ['gym_member_id' => $this->member->id]);

        // The loop continued past the unreadable row and queued the healthy credential's erase.
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $healthy->refresh()->sync_status);
        $this->assertDatabaseHas('adms_device_commands', [
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_delete',
            'command_payload->Pin' => (string) $this->member->id,
        ]);

        // The unreadable row is untouched apart from having been recorded...
        $record = DB::table('biometric_templates')->where('id', $unreadable->id)->first();
        $this->assertNotNull($record);
        $this->assertSame('not-a-valid-encrypted-payload', $record->template_ref);
        $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable->value, $record->sync_status);
        $this->assertNull($record->erasure_requested_at);

        // ...as an unresolvable erasure whose `error_type` tells it apart from the
        // blank-reference arm.
        $activity = DB::table('activity_log')
            ->where('log_name', 'biometric')
            ->where('subject_id', $this->member->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'Expected a biometric activity log entry for the erasure');
        $properties = json_decode((string) $activity->properties, true);
        $this->assertCount(1, $properties['unresolvable_erasures']);
        $this->assertSame($unreadable->id, $properties['unresolvable_erasures'][0]['template_id']);
        $this->assertSame('unconfigured_cloud_provider', $properties['unresolvable_erasures'][0]['provider']);
        $this->assertSame(DecryptException::class, $properties['unresolvable_erasures'][0]['error_type']);
    }

    /**
     * The listener must hand the service its own retry position. On the last attempt the
     * member's erasure completes even though the provider is still refusing, instead of being
     * abandoned with the job.
     */
    #[Test]
    public function the_consent_revoked_listener_completes_the_members_erasure_on_its_last_attempt(): void
    {
        $template = BiometricTemplate::factory()->cloudBacked('unconfigured_cloud_provider')->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => null,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => '{"provider":"unconfigured_cloud_provider","externalId":"face-4","meta":[]}',
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        $listener = resolve(HandleConsentRevoked::class);
        $job = Mockery::mock(Job::class);
        // The last attempt: `$tries` is 3.
        $job->shouldReceive('attempts')->andReturn(3);
        $listener->setJob($job);

        $listener->handle(new BiometricConsentRevoked($this->member->fresh()));

        // The obligation is still outstanding and retryable for the reconciler...
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->refresh()->sync_status);

        // ...but the member's own erasure is not discarded with the job.
        $this->assertDatabaseMissing('biometric_consents', ['gym_member_id' => $this->member->id]);
    }

    /**
     * The synchronous path (no queued job) has no retries to spend, so the listener must treat
     * it as the final attempt: the member's erasure completes while the provider obligation
     * stays `PendingDeletion` for the reconciler.
     */
    #[Test]
    public function the_consent_revoked_listener_completes_the_members_erasure_on_the_sync_path(): void
    {
        $template = BiometricTemplate::factory()->cloudBacked('unconfigured_cloud_provider')->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => null,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => '{"provider":"unconfigured_cloud_provider","externalId":"face-sync","meta":[]}',
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        resolve(HandleConsentRevoked::class)->handle(new BiometricConsentRevoked($this->member->fresh()));

        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->refresh()->sync_status);
        $this->assertDatabaseMissing('biometric_consents', ['gym_member_id' => $this->member->id]);
    }

    /**
     * The `sync` connection — which `phpunit.xml` and `.env.testing` force — never retries:
     * `SyncJob::attempts()` is hard-coded to `1`, so the `attempts() >= $tries` arm is
     * unreachable there. The listener must therefore recognise the connection itself (a real
     * `SyncJob`, not a missing job), or a retryable failure on the forced connection is thrown
     * and the member's own erasure never completes.
     */
    #[Test]
    public function the_consent_revoked_listener_completes_the_members_erasure_on_a_sync_job(): void
    {
        $template = BiometricTemplate::factory()->cloudBacked('unconfigured_cloud_provider')->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => null,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => '{"provider":"unconfigured_cloud_provider","externalId":"face-sync-job","meta":[]}',
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        $listener = resolve(HandleConsentRevoked::class);
        $listener->setJob(new SyncJob(app(), '{}', 'sync', 'default'));

        $listener->handle(new BiometricConsentRevoked($this->member->fresh()));

        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->refresh()->sync_status);
        $this->assertDatabaseMissing('biometric_consents', ['gym_member_id' => $this->member->id]);
    }

    /**
     * A retry still remaining must not consume the erasure: the exception propagates so the
     * queue retries, and nothing is anonymised or deleted yet.
     */
    #[Test]
    public function the_consent_revoked_listener_retries_a_non_final_attempt(): void
    {
        $template = BiometricTemplate::factory()->cloudBacked('unconfigured_cloud_provider')->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => null,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => '{"provider":"unconfigured_cloud_provider","externalId":"face-retry","meta":[]}',
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        $listener = resolve(HandleConsentRevoked::class);
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('attempts')->andReturn(1);
        $listener->setJob($job);

        $failure = null;

        try {
            $listener->handle(new BiometricConsentRevoked($this->member->fresh()));
        } catch (RuntimeException $e) {
            $failure = $e;
        }

        $this->assertInstanceOf(
            RuntimeException::class,
            $failure,
            'A non-final attempt must propagate so the queue retries.'
        );
        $this->assertStringContainsString('Biometric data deletion failed', $failure->getMessage());

        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->refresh()->sync_status);
        $this->assertDatabaseHas('biometric_consents', ['gym_member_id' => $this->member->id]);
    }

    /**
     * A fresh credential on the same PIN as the terminal reports it, through the real
     * `user_add` lifecycle: queued, dequeued, then accepted.
     */
    protected function acceptedUserAdd(?string $pin = null, ?int $deviceId = null, ?string $stage = null): AdmsDeviceCommand
    {
        $command = $this->queuedUserAdd($pin, $deviceId, $stage);

        $this->assertTrue($command->markSent());
        $this->assertTrue($command->markAcknowledged('0'));

        return $command->refresh();
    }

    /**
     * A fresh credential the terminal has dequeued but not yet acknowledged.
     */
    protected function sentUserAdd(?string $pin = null, ?int $deviceId = null, ?string $stage = null): AdmsDeviceCommand
    {
        $command = $this->queuedUserAdd($pin, $deviceId, $stage);

        $this->assertTrue($command->markSent());

        return $command->refresh();
    }

    /**
     * Queue a `user_add` for this member's PIN without driving it any further.
     */
    protected function queuedUserAdd(?string $pin = null, ?int $deviceId = null, ?string $stage = null): AdmsDeviceCommand
    {
        $pin ??= (string) $this->member->id;
        $deviceId ??= $this->deviceA->id;

        return AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $deviceId,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => $pin, 'Name' => $this->member->name],
            'status' => AdmsCommandStatusEnum::PENDING,
            'idempotency_key' => AdmsDeviceCommand::idempotencyKeyFor($this->gym->id, $deviceId, $pin, null, $stage),
        ]);
    }

    /**
     * Drive a `user_delete` through `$attempts` terminal rejections, re-driving it through the
     * reconciler between deliveries exactly as production does.
     */
    protected function driveDeleteToExhaustion(AdmsDeviceCommand $command, int $attempts): void
    {
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $this->assertTrue($command->refresh()->markSent());
            $this->assertTrue($command->markAcknowledged('rejected'));

            $command->refresh();
            $this->assertSame(AdmsCommandStatusEnum::FAILED, $command->status);
            $this->assertSame($attempt, $command->attempt_count);

            if ($attempt < $attempts) {
                $this->artisan('biometrics:reconcile-pending-deletions')->assertSuccessful();
                $this->assertSame(AdmsCommandStatusEnum::PENDING, $command->refresh()->status);
            }
        }
    }

    /**
     * The operator surface must cover both producers of an undischargeable erasure: a terminal
     * `DeletionUnresolvable` row, and a hardware row whose `user_delete` exhausted its delivery
     * budget — a state the template does not record, so the predicate reads the command log.
     * Without the second, a cap-exhausted hardware credential has no surface at all.
     */
    #[Test]
    public function the_needs_operator_predicate_covers_both_terminal_causes(): void
    {
        $cloud = BiometricTemplate::factory()->cloudBacked('unconfigured_cloud_provider')->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => null,
            'biometric_type' => BiometricTypeEnum::Face,
            'sync_status' => BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            'is_active' => false,
            'erasure_first_attempted_at' => now()->subDays(2),
        ]);

        $hardware = BiometricTemplate::factory()->deviceNative()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        $healthy = BiometricTemplate::factory()->deviceNative()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->deviceB->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        $this->assertTrue($cloud->requiresOperatorAttention());
        $this->assertFalse($hardware->requiresOperatorAttention(), 'A delete that has not exhausted its budget is not a terminal state.');

        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => AdmsCommandKey::maxAttempts(),
            'idempotency_key' => AdmsDeviceCommand::deleteIdempotencyKeyFor($this->deviceA->id, (string) $this->member->id),
        ]);

        $this->assertTrue($hardware->refresh()->requiresOperatorAttention());
        $this->assertFalse($healthy->refresh()->requiresOperatorAttention());
    }

    protected function deliveredTemplate(): BiometricTemplate
    {
        return BiometricTemplate::factory()->synced()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);
    }

    protected function sentDeleteCommand(): AdmsDeviceCommand
    {
        return AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'idempotency_key' => null,
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 1,
        ]);
    }
}
