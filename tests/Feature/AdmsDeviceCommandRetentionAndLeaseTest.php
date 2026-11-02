<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDeviceStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\Gym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Retention and delivery rules on the durable command log: the prune query must keep the
 * GDPR dead-letter evidence, and leasing must never burn an attempt on a command the ADMS
 * protocol cannot express.
 */
class AdmsDeviceCommandRetentionAndLeaseTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'device_type' => 'fingerprint',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function command(array $overrides = []): AdmsDeviceCommand
    {
        return AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => '42'],
            'status' => AdmsCommandStatusEnum::ACKNOWLEDGED,
            ...$overrides,
        ]);
    }

    /**
     * An exhausted row is the only durable evidence that an erasure was attempted and
     * abandoned. Pruning it would reset the retry counter and destroy the compliance trail.
     */
    #[Test]
    public function an_exhausted_command_is_never_pruned(): void
    {
        $exhausted = $this->command([
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => AdmsCommandKey::maxAttempts(),
            'idempotency_key' => 'user_delete:' . $this->device->id . ':42',
        ]);
        $exhausted->acknowledged_at = now()->subDays(200);
        $exhausted->updated_at = now()->subDays(200);
        $exhausted->saveQuietly();

        $prunable = (new AdmsDeviceCommand())->prunable()->pluck('id');

        $this->assertNotContains(
            $exhausted->id,
            $prunable,
            'An exhausted command is the GDPR dead-letter record and must outlive the retention window.'
        );
    }

    #[Test]
    public function an_undelivered_but_unexhausted_command_is_still_pruned(): void
    {
        $stale = $this->command(['status' => AdmsCommandStatusEnum::FAILED, 'attempt_count' => 1]);
        $stale->acknowledged_at = now()->subDays(200);
        $stale->updated_at = now()->subDays(200);
        $stale->saveQuietly();

        $prunable = (new AdmsDeviceCommand())->prunable()->pluck('id');

        $this->assertContains($stale->id, $prunable);
    }

    #[Test]
    public function a_command_with_no_attempt_count_is_pruned(): void
    {
        $row = $this->command();
        $row->acknowledged_at = now()->subDays(200);
        $row->updated_at = now()->subDays(200);
        $row->saveQuietly();

        $this->assertContains($row->id, (new AdmsDeviceCommand())->prunable()->pluck('id'));
    }

    /**
     * `DATA DELETE ... PIN=` with no PIN cannot address a record, so leasing it would consume
     * a delivery attempt for a command that is never transmitted.
     */
    #[Test]
    public function a_pin_addressed_command_without_a_usable_pin_is_never_leased(): void
    {
        $unusable = $this->command([
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => ''],
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        $this->assertNull(
            AdmsDeviceCommand::leaseNext($this->device->id),
            'A command the protocol cannot express must not be leased.'
        );
        $freshUnusable = $unusable->fresh();
        $this->assertNotNull($freshUnusable);
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $freshUnusable->status);
        $this->assertSame(0, $freshUnusable->attempt_count);
    }

    #[Test]
    public function a_device_level_command_without_a_pin_is_still_leased(): void
    {
        $this->command([
            'command_type' => 'reboot',
            'command_payload' => null,
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        $leased = AdmsDeviceCommand::leaseNext($this->device->id);

        $this->assertNotNull($leased);
        $this->assertSame('reboot', $leased->command_type);
    }

    #[Test]
    public function a_pending_command_at_the_attempt_cap_is_not_leased_again(): void
    {
        $exhausted = $this->command([
            'command_payload' => ['Pin' => '42'],
            'status' => AdmsCommandStatusEnum::PENDING,
            'attempt_count' => AdmsCommandKey::maxAttempts(),
        ]);

        $this->assertNull(AdmsDeviceCommand::leaseNext($this->device->id));
        $freshExhausted = $exhausted->fresh();
        $this->assertNotNull($freshExhausted);
        $this->assertSame(
            AdmsCommandKey::maxAttempts(),
            $freshExhausted->attempt_count,
            'An un-leased command must not have its attempt counter advanced.'
        );
    }

    #[Test]
    public function the_attempt_cap_is_never_lower_than_one(): void
    {
        config()->set('biometrics.adms.max_attempts', 0);
        $this->assertSame(1, AdmsCommandKey::maxAttempts());

        config()->set('biometrics.adms.max_attempts', -5);
        $this->assertSame(1, AdmsCommandKey::maxAttempts());

        config()->set('biometrics.adms.max_attempts', 7);
        $this->assertSame(7, AdmsCommandKey::maxAttempts());
    }

    #[Test]
    public function a_transmitted_pin_addressed_command_is_leased(): void
    {
        $deliverable = $this->command([
            'command_payload' => ['Pin' => '42'],
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        $leased = AdmsDeviceCommand::leaseNext($this->device->id);

        $this->assertNotNull($leased);
        $this->assertSame($deliverable->id, $leased->id);
        $this->assertSame(AdmsCommandStatusEnum::SENT, $leased->status);
        $this->assertSame(1, $leased->attempt_count);
    }

    /**
     * The SQL prefilter only drops a missing/empty PIN. A PIN carrying a protocol
     * control character passes it, yet serialises to an empty body, so the lease
     * must apply the same predicate as the serializer. Because the defect is a
     * property of the payload, the row is quarantined once instead of being left
     * at the head of the queue forever.
     */
    #[Test]
    public function a_pin_containing_a_control_character_is_quarantined_not_leased(): void
    {
        $unaddressable = $this->command([
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => "12\t3"],
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        $this->assertNull(
            AdmsDeviceCommand::leaseNext($this->device->id),
            'A PIN the protocol cannot address must not be leased.'
        );

        $fresh = $unaddressable->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $fresh->status);
        $this->assertSame(AdmsDeviceCommand::UNADDRESSABLE_PIN_CODE, $fresh->device_return_code);
        $this->assertSame(0, $fresh->attempt_count, 'A quarantined command must not consume an attempt.');
    }

    /**
     * The lease window is 50 rows. Fifty malformed PINs at the head of the queue used to
     * block every later command — including the `user_delete` an erasure depends on — while
     * the terminal received `OK` and re-polled. The first poll quarantines them; the next
     * selects the following window.
     */
    #[Test]
    public function fifty_unaddressable_pins_do_not_block_a_later_command(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $blocked = $this->command([
                'command_type' => 'user_delete',
                'command_payload' => ['Pin' => "12\t{$i}"],
                'status' => AdmsCommandStatusEnum::PENDING,
            ]);

            $blocked->created_at = now()->subMinutes(5);
            $blocked->saveQuietly();
        }

        $usable = $this->command([
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => '42'],
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        $this->assertNull(
            AdmsDeviceCommand::leaseNext($this->device->id),
            'The poll that discovers the malformed head returns nothing.'
        );

        $this->assertSame(
            50,
            AdmsDeviceCommand::query()
                ->where('device_return_code', AdmsDeviceCommand::UNADDRESSABLE_PIN_CODE)
                ->count(),
            'Every malformed row in the window is quarantined in one write.'
        );

        $leased = AdmsDeviceCommand::leaseNext($this->device->id);

        $this->assertNotNull($leased, 'The next poll must reach the usable command behind them.');
        $this->assertSame($usable->id, $leased->id);
    }

    /**
     * The high-water mark is what makes a dead-lettered erasure durable across an operator
     * resend: `attempt_count` is reset, `max_attempt_count` is not.
     */
    #[Test]
    public function a_reset_attempt_count_does_not_make_a_dead_lettered_row_prunable(): void
    {
        $deadLettered = $this->command([
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => AdmsCommandKey::maxAttempts(),
        ]);

        $deadLettered->update(['attempt_count' => 0]);
        $deadLettered->acknowledged_at = now()->subDays(200);
        $deadLettered->updated_at = now()->subDays(200);
        $deadLettered->saveQuietly();

        $fresh = $deadLettered->fresh();

        $this->assertSame(0, $fresh->attempt_count);
        $this->assertSame(AdmsCommandKey::maxAttempts(), $fresh->max_attempt_count);
        $this->assertNotContains(
            $fresh->id,
            (new AdmsDeviceCommand())->prunable()->pluck('id'),
            'Resetting the current budget must not destroy the dead-letter evidence.'
        );
    }

    #[Test]
    public function a_usable_pin_queued_behind_an_unaddressable_one_is_still_leased(): void
    {
        $unaddressable = $this->command([
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => "12\t3"],
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);
        $unaddressable->created_at = now()->subMinute();
        $unaddressable->saveQuietly();

        $usable = $this->command([
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => '42'],
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        $leased = AdmsDeviceCommand::leaseNext($this->device->id);

        $this->assertNotNull($leased);
        $this->assertSame($usable->id, $leased->id);
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $unaddressable->fresh()?->status);
    }

    /**
     * Covers the window where a row was leased before its payload became
     * unaddressable: the lease must be returned without spending the budget.
     */
    #[Test]
    public function releasing_an_unusable_lease_restores_the_attempt_budget(): void
    {
        $command = $this->command([
            'command_payload' => ['Pin' => "12\t3"],
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        $command->update([
            'status' => AdmsCommandStatusEnum::SENT,
            'sent_at' => now(),
            'leased_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'attempt_count' => 1,
        ]);

        $command->releaseUnusableLease();

        $fresh = $command->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $fresh->status);
        $this->assertNull($fresh->sent_at);
        $this->assertNull($fresh->leased_at);
        $this->assertNull($fresh->expires_at);
        $this->assertSame(0, $fresh->attempt_count);
    }
}
