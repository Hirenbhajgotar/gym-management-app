<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BiometricDeadLetterReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected GymMember $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'device_type' => 'fingerprint',
        ]);

        $this->member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
        ]);

        BiometricConsent::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
            'revoked_at' => null,
        ]);
    }

    #[Test]
    public function mark_acknowledged_retains_idempotency_key_and_does_not_double_count_a_delivery(): void
    {
        $idempotencyKey = "user_add:{$this->gym->id}:{$this->device->id}:{$this->member->id}:attempt_1";
        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 1,
            'idempotency_key' => $idempotencyKey,
        ]);

        $command->markAcknowledged('ERROR: memory full');

        $command->refresh();
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $command->status);
        $this->assertSame($idempotencyKey, $command->idempotency_key);
        $this->assertSame('ERROR: memory full', $command->device_return_code);

        // An attempt is one delivery to the terminal. The terminal already received and
        // rejected this delivery, so the rejection is not a second attempt. Only markSent()
        // and the stale-lease re-lease increment the counter, which keeps the device-answer
        // path and the lease-timeout path on the same budget.
        $this->assertSame(1, $command->attempt_count);
    }

    #[Test]
    public function a_terminal_that_rejects_gets_exactly_max_attempt_deliveries(): void
    {
        $maxAttempts = AdmsCommandKey::maxAttempts();
        $command = $this->makePendingUserAdd('rejects');

        $deliveries = 0;

        for ($i = 0; $i < $maxAttempts + 2; $i++) {
            $lease = AdmsDeviceCommand::leaseNext($this->device->id);

            if ($lease === null) {
                break;
            }

            $deliveries++;
            $lease->markAcknowledged('ERROR: rejected');
            $this->redriveFailed($lease->fresh());
        }

        $this->assertSame($maxAttempts, $deliveries);
        $freshCommand = $command->fresh();
        $this->assertNotNull($freshCommand);
        $this->assertSame($maxAttempts, $freshCommand->attempt_count);
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $freshCommand->status);
        $this->assertNotNull($freshCommand->idempotency_key, 'The key must survive so the retry stays deduplicated.');
    }

    #[Test]
    public function a_terminal_that_never_answers_gets_exactly_max_attempt_deliveries(): void
    {
        $maxAttempts = AdmsCommandKey::maxAttempts();
        $command = $this->makePendingUserAdd('silent');

        $deliveries = 0;

        for ($i = 0; $i < $maxAttempts + 2; $i++) {
            $lease = AdmsDeviceCommand::leaseNext($this->device->id);

            if ($lease === null) {
                break;
            }

            $deliveries++;
            $this->expireLease($lease->fresh());
        }

        $this->assertSame($maxAttempts, $deliveries);
        $freshCommand = $command->fresh();
        $this->assertNotNull($freshCommand);
        $this->assertSame($maxAttempts, $freshCommand->attempt_count);
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $freshCommand->status);
        $this->assertSame('timeout_max_attempts', $freshCommand->device_return_code);
    }

    protected function makePendingUserAdd(string $suffix): AdmsDeviceCommand
    {
        return AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id, 'Name' => $this->member->name],
            'status' => AdmsCommandStatusEnum::PENDING,
            'idempotency_key' => "user_add:{$this->gym->id}:{$this->device->id}:{$this->member->id}:{$suffix}",
        ]);
    }

    /**
     * Stand in for `biometrics:reconcile-pending-deletions` / `biometrics:reconcile-enrollments`:
     * an exhausted-but-not-yet-capped FAILED command is returned to PENDING in place.
     */
    protected function redriveFailed(?AdmsDeviceCommand $command): void
    {
        if ($command === null) {
            return;
        }

        $fresh = $command->fresh();

        if ($fresh !== null
            && $fresh->status === AdmsCommandStatusEnum::FAILED
            && $fresh->attempt_count < AdmsCommandKey::maxAttempts()) {
            $fresh->update([
                'status' => AdmsCommandStatusEnum::PENDING,
                'sent_at' => null,
                'acknowledged_at' => null,
                'leased_at' => null,
                'expires_at' => null,
                'device_return_code' => null,
            ]);
        }
    }

    /**
     * Stand in for `app:reclaim-stale-device-commands` returning a lease-expired SENT command.
     */
    protected function expireLease(?AdmsDeviceCommand $command): void
    {
        if ($command === null) {
            return;
        }

        $fresh = $command->fresh();

        if ($fresh === null) {
            return;
        }

        if ($fresh->attempt_count >= AdmsCommandKey::maxAttempts()) {
            $fresh->update([
                'status' => AdmsCommandStatusEnum::FAILED,
                'device_return_code' => 'timeout_max_attempts',
                'leased_at' => null,
                'expires_at' => null,
            ]);

            return;
        }

        $fresh->update([
            'status' => AdmsCommandStatusEnum::PENDING,
            'leased_at' => null,
            'expires_at' => null,
        ]);
    }

    #[Test]
    public function reconciler_redrives_existing_failed_command_and_dead_letters_at_max_attempts(): void
    {
        $template = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
            'hardware_confirmed_at' => now(),
        ]);

        $idempotencyKey = AdmsDeviceCommand::idempotencyKeyFor(
            $this->gym->id,
            $this->device->id,
            $this->member->id,
            $template->id,
            'reconcile'
        );

        // Seed an existing failed command with attempt_count = 1
        $failedCommand = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id, 'Name' => $this->member->name],
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => 1,
            'idempotency_key' => $idempotencyKey,
        ]);

        // First reconcile: re-drives the existing command
        $this->artisan('biometrics:reconcile-enrollments', ['--gym' => (string) $this->gym->id])
            ->expectsOutputToContain('1 command(s) queued')
            ->assertSuccessful();

        $this->assertSame(1, AdmsDeviceCommand::query()->count());
        $failedCommand->refresh();
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $failedCommand->status);

        // Simulate the next delivery: PENDING -> SENT (attempt_count 1 -> 2) -> rejected ACK.
        // The rejection does not itself consume an attempt.
        $this->assertTrue($failedCommand->markSent());
        $failedCommand->markAcknowledged('ERROR: device rejection');
        $freshCommand1 = $failedCommand->fresh();
        $this->assertNotNull($freshCommand1);
        $this->assertSame(2, $freshCommand1->attempt_count);

        // Second reconcile: re-drives again (attempt_count 2 < 3)
        $this->artisan('biometrics:reconcile-enrollments', ['--gym' => (string) $this->gym->id])
            ->expectsOutputToContain('1 command(s) queued')
            ->assertSuccessful();

        $failedCommand->refresh();
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $failedCommand->status);

        // Third delivery reaches the cap (3)
        $this->assertTrue($failedCommand->markSent());
        $failedCommand->markAcknowledged('ERROR: device rejection');
        $freshCommand2 = $failedCommand->fresh();
        $this->assertNotNull($freshCommand2);
        $this->assertSame(3, $freshCommand2->attempt_count);

        // Further reconcile: must dead-letter and NOT re-drive
        $this->artisan('biometrics:reconcile-enrollments', ['--gym' => (string) $this->gym->id])
            ->expectsOutputToContain('1 dead-lettered')
            ->assertSuccessful();

        $failedCommand->refresh();
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $failedCommand->status);
        $this->assertSame(1, AdmsDeviceCommand::query()->count());
    }

    #[Test]
    public function enrollments_reconciler_matches_dead_letter_when_idempotency_key_is_null(): void
    {
        BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now(),
            'hardware_confirmed_at' => now(),
        ]);

        // Prior command failed and has idempotency_key = null, attempt_count = 3 (exhausted)
        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id, 'Name' => $this->member->name],
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => 3,
            'idempotency_key' => null, // A failed legacy row that never received a key
        ]);

        $this->artisan('biometrics:reconcile-enrollments', ['--gym' => (string) $this->gym->id])
            ->expectsOutputToContain('1 dead-lettered')
            ->assertSuccessful();

        // No new command was created because it was matched via templateMatchesPin and recognized as dead-lettered
        $this->assertSame(1, AdmsDeviceCommand::query()->count());
    }

    #[Test]
    public function deletions_reconciler_skips_recently_exhausted_failed_commands_during_cooldown(): void
    {
        BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'enrolled_at' => now()->subDay(),
            'hardware_confirmed_at' => now()->subDay(),
        ]);

        // Prior user_delete command exhausted retries 5 minutes ago, inside the cooldown window.
        // It carries the real PIN-scoped key so the dedupe path is exercised, not bypassed.
        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => 3,
            'updated_at' => now()->subMinutes(5),
            'idempotency_key' => AdmsDeviceCommand::deleteIdempotencyKeyFor($this->device->id, (string) $this->member->id),
        ]);

        $this->artisan('biometrics:reconcile-pending-deletions')
            ->expectsOutputToContain('DEAD-LETTERED')
            ->assertSuccessful();

        // No new user_delete command should be queued during cooldown
        $this->assertSame(1, AdmsDeviceCommand::query()->where('command_type', 'user_delete')->count());
    }

    /**
     * A re-enrolment the terminal accepted after an exhausted delete is a fresh obligation: the
     * reconciler must re-drive it rather than dead-letter it during the cooldown. The credit is
     * consumed by the re-drive, so the same acknowledgement cannot start a second epoch — the
     * next exhaustion dead-letters durably instead of spinning hardware writes.
     */
    #[Test]
    public function reconciler_re_drives_a_re_enrolment_then_dead_letters_once_the_credit_is_consumed(): void
    {
        $pin = '880010';

        BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => $pin,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'enrolled_at' => now()->subDay(),
            'hardware_confirmed_at' => now()->subDay(),
        ]);

        // A delete the terminal answered and rejected up to the delivery cap, still inside the
        // cooldown (its `updated_at` is now). The explicit past `acknowledged_at` keeps the
        // first-epoch anchor deterministic: without a fresh credential this would be
        // dead-lettered.
        $delete = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => $pin],
            'idempotency_key' => AdmsDeviceCommand::deleteIdempotencyKeyFor($this->device->id, $pin),
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => AdmsCommandKey::maxAttempts(),
            'acknowledged_at' => now()->subMinutes(10),
            'sent_at' => now()->subMinutes(10),
        ]);

        // A fresh credential on the same PIN, accepted by the terminal after the delete last
        // failed — driven through the real `user_add` lifecycle.
        $userAdd = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => $pin, 'Name' => $this->member->name],
            'status' => AdmsCommandStatusEnum::PENDING,
            'idempotency_key' => AdmsDeviceCommand::idempotencyKeyFor(
                $this->gym->id,
                $this->device->id,
                $pin
            ),
        ]);

        $this->assertTrue($userAdd->markSent());
        $this->assertTrue($userAdd->markAcknowledged('0'));

        $this->artisan('biometrics:reconcile-pending-deletions')
            ->doesntExpectOutputToContain('DEAD-LETTERED')
            ->expectsOutputToContain('1 command(s) queued')
            ->assertSuccessful();

        $delete->refresh();
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $delete->status);
        $this->assertSame(0, $delete->attempt_count);
        $this->assertSame($userAdd->id, $delete->epoch_credential_user_add_id);

        // Exhaust the re-driven delete again. The consumed acknowledgement must not keep
        // resetting the cap, so every intermediate failure preserves the delivery counter...
        for ($attempt = 1; $attempt <= AdmsCommandKey::maxAttempts(); $attempt++) {
            $this->assertTrue($delete->refresh()->markSent());
            $this->assertTrue($delete->markAcknowledged('rejected'));

            if ($attempt < AdmsCommandKey::maxAttempts()) {
                $this->artisan('biometrics:reconcile-pending-deletions')
                    ->doesntExpectOutputToContain('DEAD-LETTERED')
                    ->assertSuccessful();

                $delete->refresh();
                $this->assertSame(AdmsCommandStatusEnum::PENDING, $delete->status);
                $this->assertSame($attempt, $delete->attempt_count);
            }
        }

        // ...and at the cap the reconciler dead-letters instead of writing to the hardware.
        $this->artisan('biometrics:reconcile-pending-deletions')
            ->expectsOutputToContain('DEAD-LETTERED')
            ->assertSuccessful();

        $delete->refresh();
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $delete->status);
        $this->assertSame(AdmsCommandKey::maxAttempts(), $delete->attempt_count);
        $this->assertSame(
            $userAdd->id,
            $delete->epoch_credential_user_add_id,
            'The same acknowledgement must not start a second epoch.'
        );
    }
}
