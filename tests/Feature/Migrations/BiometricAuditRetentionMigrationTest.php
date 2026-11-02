<?php

declare(strict_types=1);

namespace Tests\Feature\Migrations;

use App\Biometrics\DTOs\AdmsEventResult;
use App\Biometrics\DTOs\DoorControlResult;
use App\Biometrics\Services\BiometricReceiptService;
use App\Biometrics\Services\DoorControlService;
use App\Models\BiometricDevice;
use App\Models\BiometricEventReceipt;
use App\Models\DoorEvent;
use App\Models\Gym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the retention guarantee: deleting a terminal nulls the foreign keys but
 * must never destroy the access-control audit trail or webhook receipts.
 */
class BiometricAuditRetentionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_device_retains_access_events_and_receipts(): void
    {
        $gym = Gym::factory()->create();
        $device = BiometricDevice::factory()->for($gym)->create(['device_serial' => 'SN-RETAIN-1']);

        $event = DoorEvent::query()->create([
            'gym_id' => $gym->id,
            'device_id' => $device->id,
            'device_serial' => $device->device_serial,
            'event_type' => 'unlock_granted',
            'trigger_source' => 'biometric',
            'door_status' => 'unlocked',
        ]);

        $receipt = BiometricEventReceipt::factory()->create([
            'gym_id' => $gym->id,
            'device_id' => $device->id,
        ]);

        $device->delete();

        $this->assertDatabaseHas('door_events', [
            'id' => $event->id,
            'device_id' => null,
            'device_serial' => 'SN-RETAIN-1',
        ]);
        $this->assertDatabaseHas('biometric_event_receipts', [
            'id' => $receipt->id,
            'device_id' => null,
        ]);

        $this->assertNotNull($event->fresh());
        $this->assertNotNull($receipt->fresh());
    }

    public function test_new_door_events_snapshot_the_device_serial(): void
    {
        $device = BiometricDevice::factory()->create(['device_serial' => 'SN-SNAPSHOT-9']);

        $event = app(DoorControlService::class)->logEvent(
            $device,
            null,
            null,
            'unlock_granted',
            DoorControlResult::unlocked(),
        );

        $this->assertSame($device->id, $event->device_id);
        $this->assertSame('SN-SNAPSHOT-9', $event->device_serial);
    }

    /**
     * A receipt retained after its terminal is deleted must still say which hardware
     * delivered the event, exactly like `door_events`.
     */
    public function test_webhook_receipts_snapshot_the_device_serial_and_keep_it_after_deletion(): void
    {
        $gym = Gym::factory()->create();
        $device = BiometricDevice::factory()->for($gym)->create(['device_serial' => 'SN-RECEIPT-7']);

        $result = app(BiometricReceiptService::class)->executeIdempotent(
            $device,
            app(BiometricReceiptService::class)->generateFingerprint($device->id, 'USERINFO', now()),
            ['event' => 'attlog'],
            fn (): AdmsEventResult => AdmsEventResult::ignored('test'),
        );

        $receipt = $result->receipt;

        $this->assertSame('SN-RECEIPT-7', $receipt->device_serial);

        $device->delete();

        $this->assertDatabaseHas('biometric_event_receipts', [
            'id' => $receipt->id,
            'device_id' => null,
            'device_serial' => 'SN-RECEIPT-7',
        ]);
    }

    public function test_door_event_without_a_serial_snapshot_is_retained_with_a_null_serial(): void
    {
        $gym = Gym::factory()->create();
        $device = BiometricDevice::factory()->for($gym)->create(['device_serial' => null]);

        $event = DoorEvent::query()->create([
            'gym_id' => $gym->id,
            'device_id' => $device->id,
            'event_type' => 'unlock_denied',
            'trigger_source' => 'biometric',
            'door_status' => 'locked',
        ]);

        $device->delete();

        $this->assertDatabaseHas('door_events', [
            'id' => $event->id,
            'device_id' => null,
            'device_serial' => null,
        ]);
    }
}
