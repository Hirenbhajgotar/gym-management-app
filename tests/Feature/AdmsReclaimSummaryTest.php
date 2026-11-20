<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Biometrics\ValueObjects\ReclaimSummary;
use App\Enums\AdmsCommandStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\Gym;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\ReusesMigratedTestDatabase;
use Tests\Concerns\SkipsFilamentBoot;
use Tests\TestCase;

/**
 * The stale reclaim seam: one ledger call that owns every branch of the sweep
 * and reports what it did through a typed summary. Callers never write CAS SQL
 * or re-derive the preserved-evidence, orphan or attempt-cap rules.
 */
final class AdmsReclaimSummaryTest extends TestCase
{
    use ReusesMigratedTestDatabase;
    use SkipsFilamentBoot;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        $this->skipFilamentBoot();

        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->restoreFilamentBoot();
    }

    public function test_reclaim_expired_leases_returns_a_typed_summary_and_reclaims_a_stale_command(): void
    {
        $stale = AdmsDeviceCommand::factory()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => '42'],
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 1,
            'leased_at' => now()->subHour(),
            'expires_at' => now()->subMinute(),
        ]);

        $summary = AdmsDeviceCommand::reclaimExpiredLeases(600);

        $this->assertInstanceOf(ReclaimSummary::class, $summary);
        $this->assertSame(1, $summary->reclaimedCount());
        $this->assertSame(0, $summary->failedCount());
        $this->assertSame(0, $summary->orphanedCount());

        $stale->refresh();

        $this->assertSame(AdmsCommandStatusEnum::PENDING, $stale->status);
        $this->assertNull($stale->leased_at);
        $this->assertNull($stale->expires_at);
    }

    public function test_reclaim_expired_leases_terminalises_a_null_device_orphan_as_cancelled(): void
    {
        $orphan = AdmsDeviceCommand::factory()->create([
            'gym_id' => $this->gym->id,
            'device_id' => null,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => '42'],
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 1,
            'leased_at' => now()->subHour(),
        ]);

        $summary = AdmsDeviceCommand::reclaimExpiredLeases(600);

        $this->assertSame(1, $summary->orphanedCount());
        $this->assertSame(0, $summary->reclaimedCount());

        $orphan->refresh();

        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $orphan->status);
        $this->assertSame(AdmsDeviceCommand::TERMINAL_DELETED_CODE, $orphan->device_return_code);
        $this->assertNull($orphan->leased_at);
        $this->assertNull($orphan->expires_at);
    }

    public function test_reclaim_expired_leases_fails_an_exhausted_command_with_the_terminal_attempt_code(): void
    {
        $maxAttempts = AdmsCommandKey::maxAttempts();

        $exhausted = AdmsDeviceCommand::factory()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => '42'],
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => $maxAttempts,
            'leased_at' => now()->subHour(),
        ]);

        $summary = AdmsDeviceCommand::reclaimExpiredLeases(600);

        $this->assertSame(1, $summary->failedCount());
        $this->assertSame(0, $summary->reclaimedCount());

        $exhausted->refresh();

        $this->assertSame(AdmsCommandStatusEnum::FAILED, $exhausted->status);
        $this->assertSame(AdmsDeviceCommand::TIMEOUT_MAX_ATTEMPTS_CODE, $exhausted->device_return_code);
        $this->assertNull($exhausted->leased_at);
        $this->assertNull($exhausted->expires_at);
    }

    public function test_reclaim_expired_leases_leaves_preserved_evidence_untouched(): void
    {
        $marker = now()->subDay();

        $preserved = AdmsDeviceCommand::factory()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => '42', 'Name' => 'Preserved'],
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 1,
            'leased_at' => now()->subHour(),
            'preserved_evidence_at' => $marker,
        ]);

        $summary = AdmsDeviceCommand::reclaimExpiredLeases(600);

        $this->assertSame(0, $summary->reclaimedCount());
        $this->assertSame(0, $summary->failedCount());
        $this->assertSame(0, $summary->orphanedCount());

        $preserved->refresh();

        $this->assertSame(
            AdmsCommandStatusEnum::SENT,
            $preserved->status,
            'A preserved delivered user_add is durable evidence and must never be swept.'
        );
        $this->assertNotNull($preserved->preserved_evidence_at);
        $this->assertSame($marker->getTimestamp(), $preserved->preserved_evidence_at->getTimestamp());
        $this->assertNotNull($preserved->leased_at);
    }

    public function test_reclaim_expired_leases_skips_concurrent_cas_misses_benignly(): void
    {
        $command = AdmsDeviceCommand::factory()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => '42'],
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => 1,
            'leased_at' => now()->subHour(),
        ]);

        $event = 'eloquent.retrieved: ' . AdmsDeviceCommand::class;
        $raced = false;

        Event::listen($event, function (AdmsDeviceCommand $retrieved) use ($command, &$raced): void {
            if ($raced || $retrieved->id !== $command->id) {
                return;
            }

            // A concurrent ACK wins between the sweep's read and its compare-and-set.
            AdmsDeviceCommand::query()
                ->where('id', $command->id)
                ->update([
                    'status' => AdmsCommandStatusEnum::ACKNOWLEDGED,
                    'acknowledged_at' => now(),
                ]);

            $raced = true;
        });

        try {
            $summary = AdmsDeviceCommand::reclaimExpiredLeases(600);
        } finally {
            Event::forget($event);
        }

        $this->assertTrue($raced, 'The simulated concurrent transition must have fired.');
        $this->assertSame(1, $summary->concurrentMisses());
        $this->assertSame(0, $summary->reclaimedCount());

        $command->refresh();

        $this->assertSame(AdmsCommandStatusEnum::ACKNOWLEDGED, $command->status);
    }
}
