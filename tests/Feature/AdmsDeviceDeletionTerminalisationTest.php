<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\ValueObjects\DeletionTerminalisation;
use App\Enums\AdmsCommandStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\Gym;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\ReusesMigratedTestDatabase;
use Tests\Concerns\SkipsFilamentBoot;
use Tests\TestCase;

/**
 * The device-deletion terminalisation seam: one ledger call owns every branch of
 * "this terminal is gone", reports what it did as a typed outcome, and leaves the
 * preserved delivery evidence readable after the foreign key is nulled.
 */
final class AdmsDeviceDeletionTerminalisationTest extends TestCase
{
    use ReusesMigratedTestDatabase;
    use SkipsFilamentBoot;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected BiometricDevice $otherDevice;

    protected function setUp(): void
    {
        $this->skipFilamentBoot();

        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
            'device_serial' => 'SN-TERMINALISED',
        ]);
        $this->otherDevice = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->restoreFilamentBoot();
    }

    public function test_terminalise_for_deleted_device_returns_a_typed_outcome_and_terminalises_only_that_devices_rows(): void
    {
        $pending = AdmsDeviceCommand::factory()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'status' => AdmsCommandStatusEnum::PENDING,
            'command_payload' => ['Pin' => '41'],
        ]);

        $sent = AdmsDeviceCommand::factory()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 1,
            'leased_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'command_payload' => ['Pin' => '42'],
        ]);

        $preserved = AdmsDeviceCommand::factory()->userAdd('43', 'Durable Evidence')->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 1,
            'leased_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ]);

        $acknowledged = AdmsDeviceCommand::factory()->acknowledged()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
        ]);

        $anotherDevicesRow = AdmsDeviceCommand::factory()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->otherDevice->id,
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        $outcome = AdmsDeviceCommand::terminaliseForDeletedDevice($this->device->id);

        $this->assertInstanceOf(DeletionTerminalisation::class, $outcome);

        $pending->refresh();
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $pending->status);
        $this->assertSame(AdmsDeviceCommand::TERMINAL_DELETED_CODE, $pending->device_return_code);
        $this->assertNull($pending->leased_at);
        $this->assertNull($pending->expires_at);

        $sent->refresh();
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $sent->status);
        $this->assertSame(AdmsDeviceCommand::TERMINAL_DELETED_CODE, $sent->device_return_code);
        $this->assertNull($sent->leased_at);
        $this->assertNull($sent->expires_at);

        $preserved->refresh();
        $this->assertSame(AdmsCommandStatusEnum::SENT, $preserved->status, 'A delivered user_add stays SENT: it is the delivery evidence itself.');
        $this->assertNotNull($preserved->preserved_evidence_at);
        $this->assertNull($preserved->device_return_code, 'A preserved row is not failed or cancelled; it is stamped.');

        $this->assertSame(AdmsCommandStatusEnum::ACKNOWLEDGED, $acknowledged->refresh()->status, 'Already-terminal rows are untouched.');
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $anotherDevicesRow->refresh()->status, 'Another terminal\'s queue is untouched.');

        $this->assertSame(1, $outcome->cancelledCount());
        $this->assertSame(1, $outcome->failedCount());
        $this->assertSame(1, $outcome->preservedCount());
        $this->assertSame(2, $outcome->terminalisedCount());
        $this->assertNotEmpty($outcome->evidenceRecordedAt()->toIso8601String());
    }

    public function test_deleting_a_terminal_receives_the_typed_outcome_and_keeps_evidence_after_the_foreign_key_nulls(): void
    {
        $pending = AdmsDeviceCommand::factory()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'status' => AdmsCommandStatusEnum::PENDING,
            'command_payload' => ['Pin' => '41'],
        ]);

        $sent = AdmsDeviceCommand::factory()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 1,
            'command_payload' => ['Pin' => '42'],
        ]);

        $preserved = AdmsDeviceCommand::factory()->userAdd('43', 'Durable Evidence')->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 1,
        ]);

        $this->assertTrue($this->device->delete());

        $outcome = $this->device->lastDeletionTerminalisation();

        $this->assertInstanceOf(DeletionTerminalisation::class, $outcome);
        $this->assertSame(1, $outcome->cancelledCount());
        $this->assertSame(1, $outcome->failedCount());
        $this->assertSame(1, $outcome->preservedCount());
        $this->assertSame(2, $outcome->terminalisedCount());

        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $pending->refresh()->status);
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $sent->refresh()->status);

        // The ledger outlives the terminal: the delivery proof is still readable.
        $preserved->refresh();
        $this->assertNull($preserved->device_id);
        $this->assertSame(AdmsCommandStatusEnum::SENT, $preserved->status);
        $this->assertNotNull($preserved->preserved_evidence_at);

        $activity = Activity::query()
            ->where('subject_type', BiometricDevice::class)
            ->where('subject_id', $this->device->id)
            ->where('description', 'Biometric terminal deleted')
            ->first();

        $this->assertInstanceOf(Activity::class, $activity);
        $properties = $activity->properties;
        $this->assertNotNull($properties);

        $cancelledCount = $properties->get('cancelled_command_count');
        $this->assertIsNumeric($cancelledCount);
        $this->assertSame(2, (int) $cancelledCount);

        $preservedCount = $properties->get('preserved_delivered_user_add_count');
        $this->assertIsNumeric($preservedCount);
        $this->assertSame(1, (int) $preservedCount);

        $this->assertSame(
            $outcome->evidenceRecordedAt()->toIso8601String(),
            $properties->get('evidence_recorded_at'),
            'The audit timestamp must be the one stamped on the preserved evidence, not a second reading of the clock.'
        );
    }
}
