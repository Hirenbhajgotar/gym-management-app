<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdmsCommandStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\Gym;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\ReusesMigratedTestDatabase;
use Tests\TestCase;

/**
 * The named ledger seams that replaced the last two callers composing their own
 * updates. Each test drives the seam itself so the exact CAS guard and payload are
 * the behavioural record, not the caller's SQL shape.
 */
final class AdmsCommandLedgerTransitionSeamsTest extends TestCase
{
    use ReusesMigratedTestDatabase;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
        ]);
    }

    public function test_requeue_terminal_user_delete_preserves_the_exact_payload_and_loses_the_cas_race_benignly(): void
    {
        $credential = AdmsDeviceCommand::factory()->userAdd('41', 'Credential')->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'status' => AdmsCommandStatusEnum::ACKNOWLEDGED,
        ]);

        $delete = AdmsDeviceCommand::factory()->userDelete('41')->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => 3,
            'max_attempt_count' => 3,
            'sent_at' => now()->subHour(),
            'acknowledged_at' => now()->subHour(),
            'device_return_code' => AdmsDeviceCommand::TIMEOUT_MAX_ATTEMPTS_CODE,
            'transient_rearm_count' => 1,
            'leased_at' => now()->subMinute(),
            'expires_at' => now()->addMinute(),
        ]);

        $auditCount = Activity::query()->count();

        $requeued = $delete->requeueTerminalUserDelete(
            $this->device->id,
            0,
            true,
            $credential->id,
            $credential->user_table,
        );

        $this->assertTrue($requeued);

        $delete->refresh();

        $this->assertSame(AdmsCommandStatusEnum::PENDING, $delete->status);
        $this->assertNull($delete->sent_at);
        $this->assertNull($delete->acknowledged_at);
        $this->assertNull($delete->leased_at);
        $this->assertNull($delete->expires_at);
        $this->assertNull($delete->device_return_code);
        $this->assertSame(0, $delete->attempt_count);
        $this->assertSame(3, $delete->max_attempt_count, 'The attempt high-water mark is evidence and is never reset.');
        $this->assertSame(2, $delete->transient_rearm_count);
        $this->assertSame($credential->id, $delete->epoch_credential_user_add_id);
        $this->assertSame($credential->user_table?->value, $delete->user_table?->value);
        $this->assertSame($auditCount, Activity::query()->count(), 'The seam adds no audit entry of its own.');

        $live = AdmsDeviceCommand::factory()->userDelete('42')->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 1,
        ]);

        $this->assertFalse(
            $live->requeueTerminalUserDelete($this->device->id, 0, false, null, null),
            'A row that went live before the CAS must lose the race, not be regressed to PENDING.'
        );

        $this->assertSame(AdmsCommandStatusEnum::SENT, $live->refresh()->status);
    }

    public function test_requeue_failed_reconciliation_preserves_the_payload_and_ignores_non_failed_rows(): void
    {
        $failed = AdmsDeviceCommand::factory()->userAdd('51', 'Member')->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'status' => AdmsCommandStatusEnum::FAILED,
            'idempotency_key' => 'user_add:old',
            'attempt_count' => 2,
            'max_attempt_count' => 2,
            'sent_at' => now()->subHour(),
            'acknowledged_at' => now()->subHour(),
            'device_return_code' => '-1',
            'leased_at' => now()->subMinute(),
            'expires_at' => now()->addMinute(),
        ]);

        $auditCount = Activity::query()->count();

        $this->assertTrue($failed->requeueFailedReconciliation('user_add:new'));

        $failed->refresh();

        $this->assertSame(AdmsCommandStatusEnum::PENDING, $failed->status);
        $this->assertSame('user_add:new', $failed->idempotency_key);
        $this->assertNull($failed->sent_at);
        $this->assertNull($failed->acknowledged_at);
        $this->assertNull($failed->leased_at);
        $this->assertNull($failed->expires_at);
        $this->assertNull($failed->device_return_code);
        $this->assertSame(2, $failed->attempt_count, 'The reconcile re-drive keeps the delivery budget.');
        $this->assertSame(2, $failed->max_attempt_count);
        $this->assertSame($auditCount, Activity::query()->count(), 'The seam adds no audit entry of its own.');

        $inFlight = AdmsDeviceCommand::factory()->userAdd('52', 'Other')->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'status' => AdmsCommandStatusEnum::SENT,
        ]);

        $this->assertFalse($inFlight->requeueFailedReconciliation('user_add:other'));
        $this->assertSame(AdmsCommandStatusEnum::SENT, $inFlight->refresh()->status);
    }
}
