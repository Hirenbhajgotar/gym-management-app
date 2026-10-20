<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\BiometricDeProvisioner;
use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDeProvisionOutcome;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * One test per de-provision outcome, so each arm of `ensureUserDeleteCommand()` and each
 * early return in `deprovision()` is pinned. Previously several of these branches could be
 * deleted without turning the suite red.
 */
class BiometricDeProvisionOutcomeTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected GymMember $member;

    protected BiometricDeProvisioner $deProvisioner;

    protected function setUp(): void
    {
        parent::setUp();

        // The cloud branch must never reach a real provider endpoint.
        Http::preventStrayRequests();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
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

        $this->deProvisioner = resolve(BiometricDeProvisioner::class);
    }

    protected function pendingDeletionTemplate(?BiometricDevice $device = null): BiometricTemplate
    {
        return BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => ($device ?? $this->device)->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'enrolled_at' => now()->subDay(),
            'hardware_confirmed_at' => now()->subDay(),
        ]);
    }

    protected function deleteKey(): string
    {
        return AdmsDeviceCommand::deleteIdempotencyKeyFor($this->device->id, (string) $this->member->id);
    }

    #[Test]
    public function an_undelivered_placeholder_is_written_off_without_a_hardware_command(): void
    {
        $template = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'adms',
            'template_ref' => null,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
            'enrolled_at' => null,
            'hardware_confirmed_at' => null,
        ]);

        $this->assertSame(BiometricDeProvisionOutcome::NotDelivered, $this->deProvisioner->deprovision($template));

        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $template->sync_status);
        $this->assertDatabaseMissing('adms_device_commands', ['command_type' => 'user_delete']);
    }

    #[Test]
    public function an_active_sibling_on_the_same_device_defers_the_erasure(): void
    {
        $template = $this->pendingDeletionTemplate();

        BiometricTemplate::factory()->synced()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
        ]);

        $this->assertSame(BiometricDeProvisionOutcome::DeferredToSibling, $this->deProvisioner->deprovision($template));

        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->sync_status);
        $this->assertDatabaseMissing('adms_device_commands', ['command_type' => 'user_delete']);
    }

    /**
     * The authority must refuse to emit a command for a device belonging to another gym,
     * because the command row would be attributed to the wrong tenant.
     */
    #[Test]
    public function a_device_belonging_to_another_gym_is_refused(): void
    {
        $foreignGym = Gym::factory()->create();
        $foreignDevice = BiometricDevice::factory()->create([
            'gym_id' => $foreignGym->id,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'device_type' => 'fingerprint',
        ]);

        $template = $this->pendingDeletionTemplate($foreignDevice);

        $this->assertSame(BiometricDeProvisionOutcome::DeviceUnavailable, $this->deProvisioner->deprovision($template));

        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->sync_status);
        $this->assertDatabaseMissing('adms_device_commands', ['command_type' => 'user_delete']);
    }

    #[Test]
    public function a_device_that_is_not_adms_enabled_parks_the_erasure(): void
    {
        $this->device->update(['adms_enabled' => false]);
        $template = $this->pendingDeletionTemplate();

        $this->assertSame(BiometricDeProvisionOutcome::DeviceUnavailable, $this->deProvisioner->deprovision($template));
        $this->assertDatabaseMissing('adms_device_commands', ['command_type' => 'user_delete']);
    }

    #[Test]
    public function a_first_erasure_queues_a_command_keyed_by_pin(): void
    {
        $template = $this->pendingDeletionTemplate();

        $this->assertSame(BiometricDeProvisionOutcome::Queued, $this->deProvisioner->deprovision($template));

        $this->assertDatabaseHas('adms_device_commands', [
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'idempotency_key' => $this->deleteKey(),
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);
    }

    /**
     * ADMS erases a whole PIN, so the idempotency key must be PIN-scoped. A template-scoped key
     * would let a second template sharing the PIN insert its own command for the same record.
     */
    #[Test]
    public function a_second_erasure_for_the_same_pin_reuses_the_live_command(): void
    {
        $first = $this->pendingDeletionTemplate();
        $this->assertSame(BiometricDeProvisionOutcome::Queued, $this->deProvisioner->deprovision($first));

        $second = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'provider_type' => 'adms',
            'template_ref' => (string) $this->member->id,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'enrolled_at' => now()->subDay(),
            'hardware_confirmed_at' => now()->subDay(),
        ]);

        $this->assertSame(BiometricDeProvisionOutcome::AlreadyQueued, $this->deProvisioner->deprovision($second));

        $this->assertSame(
            1,
            AdmsDeviceCommand::query()->where('command_type', 'user_delete')->count()
        );
    }

    #[Test]
    public function a_failed_delete_below_the_cap_is_re_driven_in_place(): void
    {
        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'idempotency_key' => $this->deleteKey(),
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => 1,
            'device_return_code' => 'ERROR: rejected',
        ]);

        $template = $this->pendingDeletionTemplate();

        $this->assertSame(BiometricDeProvisionOutcome::ReDriven, $this->deProvisioner->deprovision($template));

        $command->refresh();
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $command->status);
        $this->assertNull($command->device_return_code);
        $this->assertSame(
            1,
            $command->attempt_count,
            'A FAILED re-drive is the same obligation, so its delivery budget must survive.'
        );
        $this->assertSame(
            1,
            AdmsDeviceCommand::query()->where('command_type', 'user_delete')->count(),
            'Re-driving must reuse the row rather than insert a new command.'
        );
    }

    /**
     * Past the cap the obligation cannot self-heal, so it must be reported rather than being
     * indistinguishable from a healthy idle run.
     */
    #[Test]
    public function a_failed_delete_at_the_cap_reports_exhausted_instead_of_silently_no_opping(): void
    {
        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'idempotency_key' => $this->deleteKey(),
            'status' => AdmsCommandStatusEnum::FAILED,
            // The responding-but-rejecting device class lands on exactly `maxAttempts()`, so a
            // fabricated value like 99 is unreachable and would let the cap change silently.
            'attempt_count' => AdmsCommandKey::maxAttempts(),
            'device_return_code' => 'ERROR: rejected',
        ]);

        $template = $this->pendingDeletionTemplate();

        $this->assertSame(BiometricDeProvisionOutcome::Exhausted, $this->deProvisioner->deprovision($template));

        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->sync_status);
    }

    /**
     * Archive → ACK the erase → unarchive → re-enrol → archive leaves the PIN's
     * ACKNOWLEDGED `user_delete` still holding the UNIQUE idempotency key. The
     * renewed obligation must re-drive that row in place; if it tried to insert
     * it would collide silently and the archived member would keep a live
     * credential forever.
     */
    #[Test]
    public function an_acknowledged_delete_for_a_renewed_pin_is_re_driven_in_place(): void
    {
        $acknowledged = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'idempotency_key' => $this->deleteKey(),
            'status' => AdmsCommandStatusEnum::ACKNOWLEDGED,
            'attempt_count' => 1,
            'acknowledged_at' => now()->subDay(),
            'device_return_code' => 'OK',
        ]);

        $renewed = $this->pendingDeletionTemplate();

        $this->assertSame(BiometricDeProvisionOutcome::ReDriven, $this->deProvisioner->deprovision($renewed));

        $acknowledged->refresh();
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $acknowledged->status);
        $this->assertNull($acknowledged->acknowledged_at);
        $this->assertSame(0, $acknowledged->attempt_count);

        $this->assertSame(
            1,
            AdmsDeviceCommand::query()->where('command_type', 'user_delete')->count(),
            'The renewed erase must reuse the terminal row, never insert a duplicate.'
        );

        // The renewed obligation is queued, not stuck: the reconciler now has a
        // PENDING command to drive for the archived member's PIN.
        $this->assertDatabaseHas('adms_device_commands', [
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);
    }

    #[Test]
    public function a_device_native_row_with_no_device_bound_is_written_off_as_not_delivered(): void
    {
        $template = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => null,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now()->subDay(),
        ]);

        $this->assertSame(BiometricDeProvisionOutcome::NotDelivered, $this->deProvisioner->deprovision($template));

        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $template->sync_status);
        $this->assertFalse((bool) $template->is_active);
        $this->assertDatabaseMissing('adms_device_commands', ['command_type' => 'user_delete']);
    }

    #[Test]
    public function a_cloud_row_with_no_device_bound_is_parked_for_the_provider(): void
    {
        // No terminal can hold a cloud credential, so the null-device rule must
        // not write it off as Deleted; the provider obligation stays outstanding.
        $template = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => null,
            'biometric_type' => BiometricTypeEnum::Face,
            'provider_type' => 'unconfigured_cloud_provider',
            'template_ref' => '{"provider":"unconfigured_cloud_provider","externalId":"face-9","meta":[]}',
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now()->subDay(),
        ]);

        $this->assertSame(BiometricDeProvisionOutcome::CloudRetry, $this->deProvisioner->deprovision($template));

        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->sync_status);
        $this->assertFalse((bool) $template->is_active);
        $this->assertDatabaseMissing('adms_device_commands', ['command_type' => 'user_delete']);
    }

    #[Test]
    public function the_reconciler_surfaces_an_exhausted_erasure_that_is_past_the_cooldown(): void
    {
        config()->set('biometrics.adms.retry_cooldown_hours', 0);

        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'idempotency_key' => $this->deleteKey(),
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => AdmsCommandKey::maxAttempts(),
            'updated_at' => now()->subDays(3),
            'device_return_code' => 'ERROR: rejected',
        ]);

        $this->pendingDeletionTemplate();

        $this->artisan('biometrics:reconcile-pending-deletions')
            ->expectsOutputToContain('1 template(s) still ERASURE-OUTSTANDING past the delivery cap')
            ->assertSuccessful();
    }

    /**
     * Regression: a terminal that answers and rejects must not be retried forever. With the
     * re-drive resetting `attempt_count` to 0 on every pass, the cap, the `Exhausted` outcome
     * and the dead-letter signal were structurally unreachable for the *expected* failure mode
     * — an unbounded hourly hardware write. The budget must survive a re-drive of a `FAILED`
     * row, so `maxAttempts()` deliveries end the cycle.
     */
    #[Test]
    public function a_repeatedly_rejected_delete_reaches_the_cap_and_is_exhausted(): void
    {
        $template = $this->pendingDeletionTemplate();

        $this->assertSame(BiometricDeProvisionOutcome::Queued, $this->deProvisioner->deprovision($template));

        $deliveries = 0;
        $outcome = null;

        for ($i = 0; $i < AdmsCommandKey::maxAttempts() + 2; $i++) {
            $lease = AdmsDeviceCommand::leaseNext($this->device->id);

            if ($lease === null) {
                break;
            }

            $deliveries++;
            $lease->markAcknowledged('ERROR: rejected');

            $outcome = $this->deProvisioner->deprovision($template->fresh());

            if ($outcome === BiometricDeProvisionOutcome::Exhausted) {
                break;
            }
        }

        $this->assertSame(
            AdmsCommandKey::maxAttempts(),
            $deliveries,
            'A rejecting terminal must get exactly the delivery budget, not an unbounded loop.'
        );
        $this->assertSame(BiometricDeProvisionOutcome::Exhausted, $outcome);
    }

    /**
     * A `SENT` command means the terminal has dequeued the profile and may already have applied
     * it, so the credential may exist. Writing the template off as `Deleted` (no erase queued)
     * would leave a live credential for an archived member.
     */
    #[Test]
    public function a_sent_but_unacknowledged_user_add_is_treated_as_possibly_delivered(): void
    {
        $template = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'adms',
            'template_ref' => null,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
            'enrolled_at' => null,
            'hardware_confirmed_at' => null,
        ]);

        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'status' => AdmsCommandStatusEnum::SENT,
            'idempotency_key' => AdmsDeviceCommand::idempotencyKeyFor(
                $this->gym->id,
                $this->device->id,
                $this->member->id,
                $template->id
            ),
        ]);

        $this->assertSame(BiometricDeProvisionOutcome::Queued, $this->deProvisioner->deprovision($template));

        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->sync_status);
        $this->assertDatabaseHas('adms_device_commands', [
            'command_type' => 'user_delete',
            'command_payload->Pin' => (string) $this->member->id,
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);
    }

    /**
     * The pre-flight guard, not the compare-and-set: a `SENT` command is live, so the re-drive is
     * refused before either the read or the CAS is reached. The CAS itself is defensive (its
     * window is only schedulable under concurrency) — see the comment beside it.
     */
    #[Test]
    public function a_sent_delete_command_is_refused_before_the_re_drive_cas(): void
    {
        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'idempotency_key' => $this->deleteKey(),
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 1,
            'leased_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ]);

        $template = $this->pendingDeletionTemplate();

        $this->assertSame(BiometricDeProvisionOutcome::AlreadyQueued, $this->deProvisioner->deprovision($template));

        $command->refresh();
        $this->assertSame(AdmsCommandStatusEnum::SENT, $command->status);
        $this->assertSame(1, $command->attempt_count);
    }

    /**
     * A cloud obligation whose provider persistently refuses must stop looping — but only once
     * the provider has been failing longer than the cooldown, never because a burst of retries
     * spent a count. The consent-revoked listener retries three times in about four minutes; a
     * count-based budget let exactly that terminalise a transient outage.
     */
    #[Test]
    public function a_cloud_row_whose_provider_persistently_refuses_reaches_deletion_unresolvable(): void
    {
        config()->set('biometrics.cloud_erasure_cooldown_hours', 24);

        $template = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => null,
            'biometric_type' => BiometricTypeEnum::Face,
            'provider_type' => 'unconfigured_cloud_provider',
            'template_ref' => '{"provider":"unconfigured_cloud_provider","externalId":"face-refused","meta":[]}',
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now()->subDay(),
        ]);

        // Three fast retries — the listener's whole delivery budget — must all stay retryable.
        foreach (range(1, 3) as $ignored) {
            $this->assertSame(BiometricDeProvisionOutcome::CloudRetry, $this->deProvisioner->deprovision($template->fresh()));
        }

        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $template->sync_status);
        $this->assertSame(3, $template->erasure_attempts, 'The counter is observability; the budget is time.');
        $this->assertNotNull($template->erasure_first_attempted_at);

        // Past the cooldown the row reaches the terminal state.
        $template->forceFill(['erasure_first_attempted_at' => now()->subHours(25)])->save();

        $this->assertSame(BiometricDeProvisionOutcome::CloudUnresolvable, $this->deProvisioner->deprovision($template->fresh()));

        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable, $template->sync_status);
        $this->assertSame(4, $template->erasure_attempts);

        // Terminal: the reconciler no longer re-selects it.
        $this->artisan('biometrics:reconcile-pending-deletions')
            ->expectsOutputToContain('No biometric templates pending deletion.')
            ->assertSuccessful();
    }
}
