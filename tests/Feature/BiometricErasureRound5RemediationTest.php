<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\BiometricDeProvisioner;
use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDeProvisionOutcome;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Jobs\ReleaseTenantTwilioResources;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppAccountRelease;
use App\Models\WhatsAppPhoneNumber;
use App\Services\TwilioAccountManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Round-5 remediation invariants.
 *
 * Evidence preserved for a deleted terminal must survive the reclaim sweep; every cloud
 * blank-reference obligation must have an audited human exit; the tenant and member guards
 * must read the same predicate as the operator surface; a transient re-arm is bounded; and
 * the operator requeue is a compare-and-set. Twilio resources are captured before deletion
 * and released after commit through a durable outbox.
 */
class BiometricErasureRound5RemediationTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected GymMember $member;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        // `Gym::delete()` captures the Twilio release and dispatches it after the delete
        // commits. The sync queue would run the job inside the test process and make real
        // HTTP calls, so every dispatch is captured; the job is exercised directly below.
        Queue::fake();

        $this->gym = Gym::factory()->create();
        $this->member = GymMember::factory()->create(['gym_id' => $this->gym->id]);
        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function reclaim_keeps_a_delivered_user_add_as_evidence_after_the_terminal_is_deleted(): void
    {
        $template = $this->template([
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
            'is_active' => false,
            'hardware_confirmed_at' => null,
        ]);

        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'idempotency_key' => AdmsDeviceCommand::idempotencyKeyFor(
                $this->gym->id,
                $this->device->id,
                $this->member->id,
                $template->id
            ),
            'status' => AdmsCommandStatusEnum::SENT,
            'leased_at' => now()->subDay(),
            'expires_at' => now()->subHour(),
        ]);

        $this->assertTrue($this->device->delete());

        // The tombstone marks the delivered add as preserved evidence and leaves it SENT.
        $command->refresh();
        $this->assertSame(AdmsCommandStatusEnum::SENT, $command->status);
        $this->assertNull($command->device_id);
        $this->assertNotNull(
            $command->preserved_evidence_at,
            'Preserved evidence must carry a marker so the sweep can exclude it without re-parsing intent.'
        );

        $this->artisan('app:reclaim-stale-device-commands')
            ->expectsOutputToContain('cancelled 0 orphaned commands')
            ->assertExitCode(0);

        $command->refresh();

        $this->assertSame(
            AdmsCommandStatusEnum::SENT,
            $command->status,
            'The reclaim sweep must never cancel a delivered user_add that proves the credential reached flash.'
        );
        $this->assertTrue(
            AdmsDeviceCommand::query()->preservedEvidence()->whereKey($command->id)->exists(),
            'The row must remain addressable as preserved evidence.'
        );
        $this->assertTrue(
            $template->fresh()->wasDeliveredToHardware(),
            'Delivery evidence must survive the foreign key being nulled.'
        );
    }

    #[Test]
    public function a_cloud_template_without_a_reference_is_parked_and_can_be_attested_by_an_operator(): void
    {
        $actor = User::factory()->create();

        $template = BiometricTemplate::factory()->cloudBacked()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => null,
            'template_ref' => null,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
            'is_erasure_obligation' => false,
        ]);

        $outcome = app(BiometricDeProvisioner::class)->deprovision($template);

        $this->assertSame(BiometricDeProvisionOutcome::CloudUnresolvable, $outcome);

        $template->refresh();

        $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable, $template->sync_status);
        $this->assertTrue(
            (bool) $template->is_erasure_obligation,
            'A provider that may still hold a credential must produce a durable obligation.'
        );
        $this->assertFalse(
            $template->canBeDischargedByOperator(),
            'A cloud credential is never discharged by a physical-wipe attestation.'
        );
        $this->assertTrue(
            $template->canAttestCloudErasure(),
            'The blank reference leaves attestation as the only honest exit.'
        );

        $this->assertTrue(
            $template->dischargeIfFlagged($actor, 'Biometric cloud erasure attested by operator after provider review')
        );

        $template->refresh();

        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $template->sync_status);
        $this->assertFalse((bool) $template->is_erasure_obligation);
        $this->assertFalse($template->canAttestCloudErasure());

        $activity = Activity::query()
            ->where('log_name', 'biometric')
            ->where('subject_type', BiometricTemplate::class)
            ->where('subject_id', $template->id)
            ->where('description', 'Biometric cloud erasure attested by operator after provider review')
            ->first();

        $this->assertNotNull($activity, 'The attestation must be audited, not just reflected in the row.');
        $this->assertSame($actor->id, (int) $activity->causer_id);
    }

    #[Test]
    public function the_gym_guard_sees_a_flagless_unresolvable_row(): void
    {
        $this->assertTrue($this->device->delete());

        BiometricTemplate::factory()->deviceNative()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => null,
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            'is_active' => false,
            'is_erasure_obligation' => false,
        ]);

        $this->assertTrue(
            $this->gym->fresh()->hasOutstandingErasureObligations(),
            'The guard and the operator surface must agree that a legacy flagless park is an obligation.'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('erasure obligations');

        $this->gym->fresh()->delete();
    }

    #[Test]
    public function the_gym_guard_sees_an_exhausted_hardware_delete_without_a_template_flag(): void
    {
        $template = $this->template([
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
            'is_erasure_obligation' => false,
        ]);

        $this->command('user_delete', AdmsCommandStatusEnum::FAILED, [
            'command_payload' => ['Pin' => (string) $this->member->id],
            'attempt_count' => AdmsCommandKey::maxAttempts(),
            'max_attempt_count' => AdmsCommandKey::maxAttempts(),
            'device_return_code' => '5',
        ]);

        $this->assertFalse((bool) $template->fresh()->is_erasure_obligation);
        $this->assertTrue(
            $this->gym->fresh()->hasOutstandingErasureObligations(),
            'A dead-lettered user_delete is an obligation even when the template row was never flagged.'
        );
    }

    #[Test]
    public function a_member_with_an_outstanding_obligation_cannot_be_deleted(): void
    {
        BiometricTemplate::factory()->deviceNative()->erasureObligation()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => null,
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            'is_active' => false,
        ]);

        try {
            $this->member->delete();
            $this->fail('Deleting the member would cascade-destroy their erasure obligation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('erasure obligations would be destroyed', $exception->getMessage());
        }

        $this->assertDatabaseHas('gym_members', ['id' => $this->member->id]);
        $this->assertDatabaseHas('biometric_templates', [
            'gym_member_id' => $this->member->id,
            'is_erasure_obligation' => true,
        ]);
    }

    #[Test]
    public function a_transient_timeout_is_rearmed_below_the_cap(): void
    {
        $template = $this->pendingDeletionTemplate();
        $command = $this->timedOutDeleteCommand($template, 1);

        $outcome = app(BiometricDeProvisioner::class)->deprovision($template->fresh());

        $this->assertSame(BiometricDeProvisionOutcome::ReDriven, $outcome);

        $command->refresh();

        $this->assertSame(AdmsCommandStatusEnum::PENDING, $command->status);
        $this->assertSame(2, $command->transient_rearm_count, 'A re-arm below the cap increments the counter once.');

        $fresh = $template->fresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::PendingDeletion, $fresh->sync_status);
        $this->assertFalse((bool) $fresh->is_erasure_obligation);
    }

    #[Test]
    public function a_transient_timeout_at_the_rearm_cap_parks_the_obligation(): void
    {
        $template = $this->pendingDeletionTemplate();
        $command = $this->timedOutDeleteCommand($template, AdmsDeviceCommand::MAX_TRANSIENT_REARMS);

        $outcome = app(BiometricDeProvisioner::class)->deprovision($template->fresh());

        $this->assertSame(BiometricDeProvisionOutcome::Exhausted, $outcome);

        $command->refresh();
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $command->status);
        $this->assertSame(AdmsDeviceCommand::TIMEOUT_MAX_ATTEMPTS_CODE, $command->device_return_code);

        $fresh = $template->fresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable, $fresh->sync_status);
        $this->assertTrue(
            (bool) $fresh->is_erasure_obligation,
            'A terminal that keeps timing out at the cap is no longer provably undelivered.'
        );
    }

    #[Test]
    public function requeue_for_operator_refuses_a_row_a_concurrent_ack_already_terminalised(): void
    {
        $command = $this->command('user_delete', AdmsCommandStatusEnum::FAILED, [
            'attempt_count' => AdmsCommandKey::maxAttempts(),
            'max_attempt_count' => AdmsCommandKey::maxAttempts(),
            'device_return_code' => '5',
        ]);

        // The operator's model instance is stale; the terminal ACK moved the row first.
        AdmsDeviceCommand::query()
            ->whereKey($command->id)
            ->update([
                'status' => AdmsCommandStatusEnum::ACKNOWLEDGED,
                'acknowledged_at' => now(),
            ]);

        $this->assertFalse($command->requeueForOperator());

        $command->refresh();

        $this->assertSame(AdmsCommandStatusEnum::ACKNOWLEDGED, $command->status);
        $this->assertSame(0, Activity::query()
            ->where('description', 'ADMS command re-queued by operator')
            ->where('subject_id', $command->id)
            ->count());
    }

    #[Test]
    public function deleting_a_gym_captures_the_twilio_release_intent_and_the_job_releases_it(): void
    {
        $account = $this->whatsAppAccount();

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+14155551111',
            'phone_number_sid' => 'PNtest_round5_123',
            'channel_sender_sid' => 'XEtest_round5_123',
            'country_code' => 'US',
            'status' => 'active',
            'type' => 'local',
            'purchased_at' => now(),
        ]);

        // The tenant guard requires the audited per-device path before the gym itself.
        $this->assertTrue($this->device->delete());
        $this->assertTrue($this->gym->delete());

        $release = WhatsAppAccountRelease::query()->where('gym_id', $this->gym->id)->sole();

        $this->assertSame(WhatsAppAccountRelease::STATUS_PENDING, $release->status);
        $this->assertSame('ACtest_round5', $release->twilio_account_sid);
        $this->assertSame('MGtest_round5', $release->messaging_service_sid);
        $this->assertSame('+14155551111', $release->phone_number);
        $this->assertSame('XEtest_round5_123', $release->channel_sender_sid);
        $this->assertSame('PNtest_round5_123', $release->incoming_phone_sid);

        $manager = Mockery::mock(TwilioAccountManager::class);
        $manager->shouldReceive('releaseCapturedResources')
            ->once()
            ->with(Mockery::on(fn (WhatsAppAccountRelease $candidate): bool => $candidate->id === $release->id))
            ->andReturn([
                'success' => true,
                'message' => 'All captured Twilio resources released',
                'cleaned' => [],
                'errors' => [],
            ]);

        (new ReleaseTenantTwilioResources($release->id))->handle($manager);

        $release->refresh();

        $this->assertSame(WhatsAppAccountRelease::STATUS_RELEASED, $release->status);
        $this->assertNotNull($release->released_at);
        $this->assertSame(1, $release->attempts);
        $this->assertNull($release->last_error);
    }

    #[Test]
    public function a_failed_twilio_release_stays_in_the_outbox_for_the_recovery_sweep(): void
    {
        $this->whatsAppAccount();

        $this->assertTrue($this->device->delete());
        $this->assertTrue($this->gym->delete());

        $release = WhatsAppAccountRelease::query()->where('gym_id', $this->gym->id)->sole();

        $manager = Mockery::mock(TwilioAccountManager::class);
        $manager->shouldReceive('releaseCapturedResources')
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Twilio release completed with errors',
                'cleaned' => [],
                'errors' => ['subaccount: unavailable'],
            ]);

        try {
            (new ReleaseTenantTwilioResources($release->id))->handle($manager);
            $this->fail('A release the provider did not confirm must be retried, not marked done.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('could not be released', $exception->getMessage());
        }

        $release->refresh();

        $this->assertSame(WhatsAppAccountRelease::STATUS_FAILED, $release->status);
        $this->assertStringContainsString('subaccount: unavailable', (string) $release->last_error);
        $this->assertSame(1, $release->attempts);
        $this->assertNull($release->released_at);

        Queue::fake();

        $this->artisan('whatsapp:release-pending-accounts')
            ->assertExitCode(0);

        Queue::assertPushed(ReleaseTenantTwilioResources::class, fn (ReleaseTenantTwilioResources $job): bool => $job->releaseId === $release->id);
    }

    #[Test]
    public function delete_quietly_cannot_bypass_the_biometric_guard(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('it still has biometric terminals');

        $this->gym->deleteQuietly();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function template(array $attributes = []): BiometricTemplate
    {
        return BiometricTemplate::factory()->deviceNative()->create(array_merge([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $this->device->id,
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ], $attributes));
    }

    private function pendingDeletionTemplate(): BiometricTemplate
    {
        return $this->template([
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);
    }

    private function timedOutDeleteCommand(BiometricTemplate $template, int $rearmCount): AdmsDeviceCommand
    {
        $cooldown = config()->integer('biometrics.adms.retry_cooldown_hours', 24);

        $command = $this->command('user_delete', AdmsCommandStatusEnum::FAILED, [
            'command_payload' => ['Pin' => (string) $this->member->id],
            'idempotency_key' => AdmsCommandKey::buildUserDelete(
                $this->device->id,
                $template->resolvedCommandPin()
            ),
            'attempt_count' => AdmsCommandKey::maxAttempts(),
            'max_attempt_count' => AdmsCommandKey::maxAttempts(),
            'device_return_code' => AdmsDeviceCommand::TIMEOUT_MAX_ATTEMPTS_CODE,
            'transient_rearm_count' => $rearmCount,
        ]);

        $command->forceFill(['updated_at' => now()->subHours($cooldown + 1)])->saveQuietly();

        return $command;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function command(string $type, AdmsCommandStatusEnum $status, array $attributes = []): AdmsDeviceCommand
    {
        return AdmsDeviceCommand::query()->forceCreate(array_merge([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => $type,
            'command_payload' => ['Pin' => (string) $this->member->id],
            'idempotency_key' => $type . ':' . $this->device->id . ':' . $this->member->id . ':' . uniqid('', true),
            'status' => $status,
        ], $attributes));
    }

    private function whatsAppAccount(): WhatsAppAccount
    {
        return WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest_round5',
            'twilio_auth_token' => 'test_token_round5',
            'messaging_service_sid' => 'MGtest_round5',
            'status' => 'active',
            'verification_status' => 'verified',
            'business_name' => 'Round Five Gym',
            'business_email' => 'round5@gym.test',
        ]);
    }
}
