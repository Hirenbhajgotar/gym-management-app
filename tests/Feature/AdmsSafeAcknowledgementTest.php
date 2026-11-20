<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\ValueObjects\AcknowledgementOutcome;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\DefaultRole;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\User;
use Tests\Concerns\ReusesMigratedTestDatabase;
use Tests\Concerns\SkipsFilamentBoot;
use Tests\TestCase;

/**
 * The safe acknowledgement seam: one call, valid on any command state, returning
 * a typed outcome. Duplicate or stale replies are ignored and a rejected reply
 * never overwrites a newer state.
 */
final class AdmsSafeAcknowledgementTest extends TestCase
{
    use ReusesMigratedTestDatabase;
    use SkipsFilamentBoot;

    protected User $user;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        $this->skipFilamentBoot();

        parent::setUp();

        config(['biometrics.adms.require_comm_key' => false]);

        $this->user = User::factory()->create();
        $this->gym = Gym::factory()
            ->hasAttached($this->user, ['role' => DefaultRole::GYM_OWNER->value], 'users')
            ->create();

        $this->device = BiometricDevice::factory()
            ->admsEnabled('SN-SAFE-ACK-001')
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'purpose' => BiometricDevicePurpose::AttendanceOnly,
            ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->restoreFilamentBoot();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rebootCommand(AdmsCommandStatusEnum $status, array $attributes = []): AdmsDeviceCommand
    {
        return AdmsDeviceCommand::factory()->create(array_merge([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'device_serial' => $this->device->device_serial,
            'command_type' => 'reboot',
            'status' => $status,
        ], $attributes));
    }

    public function test_acknowledge_returns_a_typed_outcome_and_transitions_a_sent_command(): void
    {
        $command = $this->rebootCommand(AdmsCommandStatusEnum::SENT);

        $outcome = $command->acknowledge('0');

        $this->assertInstanceOf(AcknowledgementOutcome::class, $outcome);
        $this->assertTrue($outcome->wasApplied());
        $this->assertSame(AdmsCommandStatusEnum::ACKNOWLEDGED, $outcome->resultingStatus());
        $this->assertSame(AdmsCommandStatusEnum::ACKNOWLEDGED, $command->fresh()->status);
    }

    public function test_acknowledge_is_safe_on_pending_and_terminal_states_without_overwriting_them(): void
    {
        $pending = $this->rebootCommand(AdmsCommandStatusEnum::PENDING);
        $pendingOutcome = $pending->acknowledge('0');

        $this->assertFalse($pendingOutcome->wasApplied());
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $pendingOutcome->resultingStatus());
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $pending->fresh()->status);

        $acknowledged = $this->rebootCommand(AdmsCommandStatusEnum::ACKNOWLEDGED, [
            'acknowledged_at' => now(),
            'device_return_code' => '0',
        ]);
        // A stale reply carrying a different code must not overwrite the settled state.
        $duplicateOutcome = $acknowledged->acknowledge('ERROR: stale');

        $this->assertFalse($duplicateOutcome->wasApplied());
        $this->assertSame(AdmsCommandStatusEnum::ACKNOWLEDGED, $duplicateOutcome->resultingStatus());
        $this->assertSame(AdmsCommandStatusEnum::ACKNOWLEDGED, $acknowledged->fresh()->status);
        $this->assertSame('0', $acknowledged->fresh()->device_return_code);

        $cancelled = $this->rebootCommand(AdmsCommandStatusEnum::CANCELLED);
        $cancelledOutcome = $cancelled->acknowledge('0');

        $this->assertFalse($cancelledOutcome->wasApplied());
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $cancelledOutcome->resultingStatus());
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $cancelled->fresh()->status);
    }

    public function test_acknowledge_reports_the_failed_status_for_a_rejected_reply(): void
    {
        $command = $this->rebootCommand(AdmsCommandStatusEnum::SENT);

        $outcome = $command->acknowledge('ERROR: rejected');

        $this->assertTrue($outcome->wasApplied());
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $outcome->resultingStatus());
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $command->fresh()->status);
        $this->assertFalse($outcome->wasAppliedAndAcknowledged());
    }

    public function test_configured_deletion_convergence_codes_resolve_to_acknowledged(): void
    {
        config()->set('biometrics.adms.deletion_convergence_codes', ['not found']);

        $command = AdmsDeviceCommand::factory()->userDelete('123')->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'device_serial' => $this->device->device_serial,
            'status' => AdmsCommandStatusEnum::SENT,
            'sent_at' => now(),
        ]);

        $outcome = $command->acknowledge('  NOT FOUND  ');

        $this->assertTrue($outcome->wasApplied());
        $this->assertSame(AdmsCommandStatusEnum::ACKNOWLEDGED, $outcome->resultingStatus());
        $this->assertSame(AdmsCommandStatusEnum::ACKNOWLEDGED, $command->fresh()->status);
    }

    public function test_acknowledge_does_not_change_the_monotonic_high_water_mark(): void
    {
        $command = $this->rebootCommand(AdmsCommandStatusEnum::SENT, [
            'attempt_count' => 2,
            'max_attempt_count' => 2,
        ]);

        $outcome = $command->acknowledge('0');

        $this->assertTrue($outcome->wasApplied());
        $fresh = $command->fresh();
        $this->assertSame(2, $fresh->attempt_count);
        $this->assertSame(2, $fresh->max_attempt_count);
    }

    public function test_was_applied_and_acknowledged_is_true_only_for_an_applied_acknowledgement(): void
    {
        $applied = $this->rebootCommand(AdmsCommandStatusEnum::SENT)->acknowledge('0');
        $ignored = $this->rebootCommand(AdmsCommandStatusEnum::ACKNOWLEDGED, [
            'acknowledged_at' => now(),
            'device_return_code' => '0',
        ])->acknowledge('0');

        $this->assertTrue($applied->wasAppliedAndAcknowledged());
        $this->assertFalse($ignored->wasAppliedAndAcknowledged());
    }

    /**
     * Drive an acknowledgement through the protocol adapter exactly as a terminal does.
     */
    private function postAcknowledgement(int $commandId, string $returnCode): void
    {
        $this->call(
            'POST',
            "/api/iclock/devicecmd?SN={$this->device->device_serial}",
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            "ID={$commandId}&Return={$returnCode}"
        )->assertOk();
    }

    public function test_devicecmd_notifies_the_reboot_initiator_only_for_an_applied_acknowledgement(): void
    {
        $command = $this->rebootCommand(AdmsCommandStatusEnum::SENT, [
            'command_payload' => ['initiated_by_user_id' => $this->user->id],
            'sent_at' => now(),
            'attempt_count' => 1,
        ]);

        $this->assertSame(0, $this->user->notifications()->count());

        $this->postAcknowledgement($command->id, '0');

        $this->assertSame(AdmsCommandStatusEnum::ACKNOWLEDGED, $command->fresh()->status);
        $this->assertSame(1, $this->user->notifications()->count());

        // A duplicate reply is ignored by the interface and must not re-notify.
        $this->postAcknowledgement($command->id, '0');

        $this->assertSame(1, $this->user->notifications()->count());
    }

    public function test_devicecmd_does_not_notify_when_the_acknowledgement_is_rejected(): void
    {
        $command = $this->rebootCommand(AdmsCommandStatusEnum::SENT, [
            'command_payload' => ['initiated_by_user_id' => $this->user->id],
            'sent_at' => now(),
            'attempt_count' => 1,
        ]);

        $this->postAcknowledgement($command->id, 'ERROR: rejected');

        $this->assertSame(AdmsCommandStatusEnum::FAILED, $command->fresh()->status);
        $this->assertSame(0, $this->user->notifications()->count());
    }
}
