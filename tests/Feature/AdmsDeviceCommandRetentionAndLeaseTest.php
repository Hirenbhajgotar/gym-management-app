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
     * `DATA DELETE user Pin=` with no PIN cannot address a record, so leasing it would consume
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
}
