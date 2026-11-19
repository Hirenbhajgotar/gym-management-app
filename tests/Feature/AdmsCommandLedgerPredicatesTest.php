<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\Gym;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ReusesMigratedTestDatabase;
use Tests\TestCase;

/**
 * The ADMS command ledger's scan predicates have one definition each, so leasing,
 * reclaim and retention cannot drift apart. Nothing here changes what those scans
 * do; it proves they all cross the same seam.
 */
class AdmsCommandLedgerPredicatesTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function command(array $overrides = []): AdmsDeviceCommand
    {
        return AdmsDeviceCommand::factory()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            ...$overrides,
        ]);
    }

    /**
     * Leasing and reclaim must agree on what "stale lease" means. The three shapes
     * that count as stale are checked against the one scope both scans will call.
     */
    #[Test]
    public function the_stale_lease_predicate_is_one_definition_for_leasing_and_reclaim(): void
    {
        $neverLeased = $this->command([
            'status' => AdmsCommandStatusEnum::SENT,
            'leased_at' => null,
            'expires_at' => null,
        ]);
        $oldLease = $this->command([
            'status' => AdmsCommandStatusEnum::SENT,
            'leased_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
        ]);
        $expiredAt = $this->command([
            'status' => AdmsCommandStatusEnum::SENT,
            'leased_at' => now(),
            'expires_at' => now()->subMinute(),
        ]);
        $freshLease = $this->command([
            'status' => AdmsCommandStatusEnum::SENT,
            'leased_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $staleIds = AdmsDeviceCommand::query()
            ->whereKey([$neverLeased->id, $oldLease->id, $expiredAt->id, $freshLease->id])
            ->staleLease(600)
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing(
            [$neverLeased->id, $oldLease->id, $expiredAt->id],
            $staleIds
        );
    }

    /**
     * Preserved evidence is excluded by one definition, not a raw `whereNull` retyped
     * in each scan. Leasing, reclaim and retention all cross that same scope.
     */
    #[Test]
    public function the_preserved_evidence_exclusion_is_one_definition_for_leasing_reclaim_and_retention(): void
    {
        $preserved = $this->command([
            'status' => AdmsCommandStatusEnum::SENT,
            'preserved_evidence_at' => now(),
        ]);
        $ordinary = $this->command([
            'status' => AdmsCommandStatusEnum::SENT,
        ]);

        $remaining = AdmsDeviceCommand::query()
            ->whereKey([$preserved->id, $ordinary->id])
            ->withoutPreservedEvidence()
            ->pluck('id')
            ->all();

        $this->assertSame([$ordinary->id], $remaining);
    }

    /**
     * Leasing must not re-arm a preserved `SENT` row: doing so would mutate durable
     * evidence. The row is bound to a live device and stale here so that the only
     * reason it is skipped is the shared preserved-evidence predicate.
     */
    #[Test]
    public function leasing_never_selects_preserved_evidence(): void
    {
        $preserved = $this->command([
            'status' => AdmsCommandStatusEnum::SENT,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => '42', 'Name' => 'A'],
            'leased_at' => now()->subHour(),
            'expires_at' => now()->subMinute(),
            'preserved_evidence_at' => now(),
        ]);

        $this->assertNull(AdmsDeviceCommand::leaseNext($this->device->id));
        $fresh = $preserved->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(0, $fresh->attempt_count);
    }

    /**
     * Serialization, the lease window and quarantine all decide "usable PIN" through
     * the one predicate, so a PIN the serializer rejects is never leased and is
     * quarantined with the causal code instead.
     */
    #[Test]
    public function the_usable_pin_predicate_is_shared_by_serialization_leasing_and_quarantine(): void
    {
        $unusablePin = "12\t3";

        $command = $this->command([
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => $unusablePin],
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        $this->assertFalse(AdmsDeviceCommand::pinIsUsable($unusablePin));
        $this->assertSame('', $command->toAdmsCommandString());

        $this->assertNull(AdmsDeviceCommand::leaseNext($this->device->id));
        $fresh = $command->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $fresh->status);
        $this->assertSame(AdmsDeviceCommand::UNADDRESSABLE_PIN_CODE, $fresh->device_return_code);
    }

    /**
     * The dead-letter scope and the reclaim sweep agree on the one terminal-attempt
     * code. The de-provisioner reads the same constant for its transient-rearm check.
     */
    #[Test]
    public function the_terminal_attempt_code_is_one_value_for_the_scope_and_reclaim(): void
    {
        $timedOut = $this->command([
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => AdmsCommandKey::maxAttempts(),
            'device_return_code' => AdmsDeviceCommand::TIMEOUT_MAX_ATTEMPTS_CODE,
            'leased_at' => now()->subHour(),
        ]);
        $rejected = $this->command([
            'status' => AdmsCommandStatusEnum::SENT,
            'attempt_count' => AdmsCommandKey::maxAttempts(),
            'device_return_code' => '-1',
            'leased_at' => now()->subHour(),
        ]);

        $deadLettered = AdmsDeviceCommand::query()
            ->whereKey([$timedOut->id, $rejected->id])
            ->deadLettered()
            ->pluck('id')
            ->all();
        $this->assertSame([$rejected->id], $deadLettered);

        $this->artisan('app:reclaim-stale-device-commands')
            ->assertExitCode(0);

        $fresh = $timedOut->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(AdmsDeviceCommand::TIMEOUT_MAX_ATTEMPTS_CODE, $fresh->device_return_code);
    }
}
