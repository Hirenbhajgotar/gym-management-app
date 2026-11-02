<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Round-4 erasure lifecycle invariants.
 *
 * A flag that records an outstanding erasure must always have a route to discharge, and
 * must be cleared by any route that actually erases. The terminal-state write and the flag
 * are one transition; the ledger keeps delivered evidence across a terminal delete; a
 * transient timeout is not abandonment; and a gym with an outstanding obligation is not
 * deletable.
 */
class BiometricErasureLifecycleRound4Test extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected GymMember $member;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->member = GymMember::factory()->create(['gym_id' => $this->gym->id]);
        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
        ]);
    }

    #[Test]
    public function parking_an_obligation_writes_the_flag_state_and_an_audit_entry(): void
    {
        $template = $this->template();

        $template->parkAsUnresolvableObligation('delivery budget exhausted', [
            'command_id' => 42,
            'device_return_code' => '5',
        ]);

        $fresh = $this->freshTemplate($template);

        $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable, $fresh->sync_status);
        $this->assertTrue($fresh->is_erasure_obligation);
        $this->assertFalse($fresh->is_active);

        $activity = Activity::query()
            ->where('description', 'Biometric erasure obligation parked for operator')
            ->where('subject_id', $template->id)
            ->first();

        $this->assertNotNull($activity, 'Entering the obligation state must be audited, not only logged.');
        $this->assertSame('delivery budget exhausted', $activity->properties['reason']);
        $this->assertSame(42, (int) $activity->properties['command_id']);
        $this->assertSame('5', $activity->properties['device_return_code']);
        $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable->value, $activity->properties['to_status']);
    }

    #[Test]
    public function a_confirmed_erase_clears_the_obligation_flag(): void
    {
        $template = $this->template([
            'sync_status' => BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            'is_erasure_obligation' => true,
            'is_active' => false,
        ]);

        $template->markErasureDischarged();

        $fresh = $this->freshTemplate($template);

        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $fresh->sync_status);
        $this->assertFalse($fresh->is_erasure_obligation);
        $this->assertFalse($fresh->is_active);
    }

    #[Test]
    public function a_terminal_ack_clears_the_obligation_flag(): void
    {
        $template = $this->template([
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_erasure_obligation' => true,
            'is_active' => false,
        ]);

        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'idempotency_key' => AdmsCommandKey::buildUserDelete($this->device->id, (string) $this->member->id),
            'status' => AdmsCommandStatusEnum::SENT,
        ]);

        $this->assertTrue($command->markAcknowledged('0'));

        $fresh = $this->freshTemplate($template);

        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $fresh->sync_status);
        $this->assertFalse(
            $fresh->is_erasure_obligation,
            'A terminal ACK is a confirmed erase; the obligation flag must not survive it.'
        );
    }

    #[Test]
    public function a_sent_user_add_survives_terminal_deletion_as_delivery_evidence(): void
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
        ]);

        $this->assertTrue($this->device->delete());

        $command = $this->freshCommand($command);
        $fresh = $this->freshTemplate($template);

        $this->assertSame(AdmsCommandStatusEnum::SENT, $command->status, 'A delivered user_add is evidence, not an orphan to cancel.');
        $this->assertNull($command->device_id);
        $this->assertTrue(
            $fresh->wasDeliveredToHardware(),
            'The ledger must still prove delivery after the foreign key is nulled.'
        );
        $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable, $fresh->sync_status);
        $this->assertTrue($fresh->is_erasure_obligation);
    }

    #[Test]
    public function terminal_deletion_terminalises_pending_and_sent_rows_with_a_cause(): void
    {
        $pendingAdd = $this->command('user_add', AdmsCommandStatusEnum::PENDING);
        $sentAdd = $this->command('user_add', AdmsCommandStatusEnum::SENT);
        $sentDelete = $this->command('user_delete', AdmsCommandStatusEnum::SENT);

        $this->assertTrue($this->device->delete());

        $pendingAdd = $this->freshCommand($pendingAdd);
        $sentAdd = $this->freshCommand($sentAdd);
        $sentDelete = $this->freshCommand($sentDelete);

        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $pendingAdd->status);
        $this->assertSame(AdmsDeviceCommand::TERMINAL_DELETED_CODE, $pendingAdd->device_return_code);
        $this->assertSame(AdmsCommandStatusEnum::SENT, $sentAdd->status);
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $sentDelete->status);
        $this->assertSame(AdmsDeviceCommand::TERMINAL_DELETED_CODE, $sentDelete->device_return_code);
    }

    #[Test]
    public function delete_quietly_still_runs_the_tombstone_and_writes_the_audit(): void
    {
        $template = $this->template();

        $this->assertTrue($this->device->deleteQuietly());

        $this->assertDatabaseMissing('biometric_devices', ['id' => $this->device->id]);

        $fresh = $this->freshTemplate($template);
        $this->assertNull($fresh->enrolled_by_device_id);
        $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable, $fresh->sync_status);
        $this->assertTrue($fresh->is_erasure_obligation);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Biometric terminal deleted',
            'subject_type' => BiometricDevice::class,
            'subject_id' => $this->device->id,
        ]);
    }

    #[Test]
    public function mark_sent_raises_the_high_water_mark(): void
    {
        $command = $this->command('user_delete', AdmsCommandStatusEnum::PENDING, [
            'attempt_count' => AdmsCommandKey::maxAttempts() - 1,
            'max_attempt_count' => 0,
        ]);

        $this->assertTrue($command->markSent());

        $fresh = $this->freshCommand($command);
        $this->assertSame(AdmsCommandKey::maxAttempts(), $fresh->attempt_count);
        $this->assertSame(
            AdmsCommandKey::maxAttempts(),
            $fresh->max_attempt_count,
            'The SQL high-water update is what makes the dead-letter evidence durable.'
        );
    }

    #[Test]
    public function exhausted_reads_the_current_budget_and_ever_exhausted_reads_the_high_water(): void
    {
        $current = $this->command('user_delete', AdmsCommandStatusEnum::FAILED, [
            'attempt_count' => AdmsCommandKey::maxAttempts(),
            'max_attempt_count' => AdmsCommandKey::maxAttempts(),
            'device_return_code' => '5',
        ]);

        $reset = $this->command('user_delete', AdmsCommandStatusEnum::FAILED, [
            'attempt_count' => 0,
            'max_attempt_count' => AdmsCommandKey::maxAttempts(),
            'device_return_code' => '5',
        ]);

        $this->assertContains($current->id, AdmsDeviceCommand::query()->exhausted()->pluck('id')->all());
        $this->assertNotContains($reset->id, AdmsDeviceCommand::query()->exhausted()->pluck('id')->all());

        $everExhausted = AdmsDeviceCommand::query()->everExhausted()->pluck('id')->all();
        $this->assertContains($current->id, $everExhausted);
        $this->assertContains($reset->id, $everExhausted);
    }

    #[Test]
    public function a_transient_timeout_is_not_a_dead_letter(): void
    {
        $timeout = $this->command('user_delete', AdmsCommandStatusEnum::FAILED, [
            'attempt_count' => AdmsCommandKey::maxAttempts(),
            'max_attempt_count' => AdmsCommandKey::maxAttempts(),
            'device_return_code' => AdmsDeviceCommand::TIMEOUT_MAX_ATTEMPTS_CODE,
        ]);

        $rejected = $this->command('user_delete', AdmsCommandStatusEnum::FAILED, [
            'attempt_count' => AdmsCommandKey::maxAttempts(),
            'max_attempt_count' => AdmsCommandKey::maxAttempts(),
            'device_return_code' => '5',
        ]);

        $deadLettered = AdmsDeviceCommand::query()->deadLettered()->pluck('id')->all();

        $this->assertNotContains($timeout->id, $deadLettered);
        $this->assertContains($rejected->id, $deadLettered);
    }

    #[Test]
    public function requeue_for_operator_resets_the_budget_and_audits_the_actor(): void
    {
        $actor = User::factory()->create();
        $command = $this->command('user_delete', AdmsCommandStatusEnum::FAILED, [
            'attempt_count' => AdmsCommandKey::maxAttempts(),
            'max_attempt_count' => AdmsCommandKey::maxAttempts(),
            'device_return_code' => '5',
        ]);

        $command->requeueForOperator($actor);

        $fresh = $this->freshCommand($command);
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $fresh->status);
        $this->assertSame(0, $fresh->attempt_count);
        $this->assertSame(AdmsCommandKey::maxAttempts(), $fresh->max_attempt_count);
        $this->assertNull($fresh->device_return_code);

        $activity = Activity::query()
            ->where('description', 'ADMS command re-queued by operator')
            ->where('subject_id', $command->id)
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($actor->id, (int) $activity->causer_id);
        $this->assertSame(AdmsCommandKey::maxAttempts(), (int) $activity->properties['previous_attempt_count']);
        $this->assertSame('5', $activity->properties['previous_device_return_code']);
    }

    #[Test]
    public function a_gym_with_an_outstanding_obligation_is_not_deletable(): void
    {
        $template = $this->template();

        // Retiring the terminal is the audited path, but it parks the credential as an
        // obligation. The gym must then refuse deletion until that obligation is discharged.
        $this->assertTrue($this->device->delete());
        $this->assertTrue($this->freshTemplate($template)->is_erasure_obligation);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('erasure obligations');

        $this->gym->delete();
    }

    #[Test]
    public function retiring_terminals_uses_the_audited_path_and_parks_the_credential(): void
    {
        $template = $this->template();

        $this->gym->retireBiometricTerminals();

        $this->assertDatabaseMissing('biometric_devices', ['id' => $this->device->id]);

        $fresh = $this->freshTemplate($template);
        $this->assertNull($fresh->enrolled_by_device_id);
        $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable, $fresh->sync_status);
        $this->assertTrue($fresh->is_erasure_obligation);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Biometric terminal deleted',
            'subject_type' => BiometricDevice::class,
            'subject_id' => $this->device->id,
        ]);
    }

    #[Test]
    public function the_repair_migration_clears_a_stale_flag_on_a_deleted_row(): void
    {
        $template = $this->template([
            'enrolled_by_device_id' => null,
            'sync_status' => BiometricTemplateSyncStatusEnum::Deleted,
            'is_erasure_obligation' => true,
            'is_active' => false,
        ]);

        $migration = require database_path('migrations/2026_11_01_000200_clear_erasure_obligation_on_deleted_templates.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $migration->up();

        $this->assertFalse($this->freshTemplate($template)->is_erasure_obligation);
    }

    #[Test]
    public function the_reconciler_does_not_dead_letter_a_transient_timeout(): void
    {
        $template = $this->template([
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        $this->command('user_delete', AdmsCommandStatusEnum::FAILED, [
            'attempt_count' => AdmsCommandKey::maxAttempts(),
            'max_attempt_count' => AdmsCommandKey::maxAttempts(),
            'device_return_code' => AdmsDeviceCommand::TIMEOUT_MAX_ATTEMPTS_CODE,
            'idempotency_key' => AdmsCommandKey::buildUserDelete($this->device->id, (string) $this->member->id),
            'updated_at' => now(),
        ]);

        $this->artisan('biometrics:reconcile-pending-deletions')->assertSuccessful();

        $fresh = $this->freshTemplate($template);

        $this->assertSame(
            BiometricTemplateSyncStatusEnum::PendingDeletion,
            $fresh->sync_status,
            'A terminal that was merely offline is a transient outage, not an abandoned erasure.'
        );
        $this->assertFalse($fresh->is_erasure_obligation);
    }

    #[Test]
    public function the_reconciler_dead_letters_a_delivered_rejection_at_the_cap(): void
    {
        $template = $this->template([
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        $this->command('user_delete', AdmsCommandStatusEnum::FAILED, [
            'attempt_count' => AdmsCommandKey::maxAttempts(),
            'max_attempt_count' => AdmsCommandKey::maxAttempts(),
            'device_return_code' => '5',
            'idempotency_key' => AdmsCommandKey::buildUserDelete($this->device->id, (string) $this->member->id),
            'updated_at' => now(),
        ]);

        $this->artisan('biometrics:reconcile-pending-deletions')
            ->expectsOutputToContain('DEAD-LETTERED')
            ->assertSuccessful();

        $fresh = $this->freshTemplate($template);

        $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable, $fresh->sync_status);
        $this->assertTrue($fresh->is_erasure_obligation);
    }

    #[Test]
    public function cache_invalidation_seeds_the_generation_on_the_database_store(): void
    {
        $originalStore = config('cache.default');
        config()->set('cache.default', 'database');

        try {
            Cache::store('database')->flush();

            $template = $this->template();
            $key = BiometricTemplate::templateRefCacheGenerationKey($this->gym->id);

            $this->assertSame(
                2,
                (int) Cache::store('database')->get($key, 0),
                'Creating a template seeds the generation (1) and bumps it once (2).'
            );

            $template->clearTemplateRefCache();

            $this->assertGreaterThanOrEqual(
                3,
                (int) Cache::store('database')->get($key, 0),
                'Invalidation must actually bump the generation on the database store, not silently no-op.'
            );
        } finally {
            config()->set('cache.default', $originalStore);
        }
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

    private function freshTemplate(BiometricTemplate $template): BiometricTemplate
    {
        $fresh = $template->fresh();

        $this->assertInstanceOf(BiometricTemplate::class, $fresh);

        return $fresh;
    }

    private function freshCommand(AdmsDeviceCommand $command): AdmsDeviceCommand
    {
        $fresh = $command->fresh();

        $this->assertInstanceOf(AdmsDeviceCommand::class, $fresh);

        return $fresh;
    }
}
