<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\BiometricDataDeletionService;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmsDeviceCommandCancellationTest extends TestCase
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
        ]);
    }

    public function test_mark_cancelled_sets_cancelled_status_and_nulls_idempotency_key(): void
    {
        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => '1001', 'Name' => 'Test User'],
            'status' => AdmsCommandStatusEnum::PENDING,
            'idempotency_key' => "user_add:{$this->device->id}:1001",
        ]);

        $result = $command->markCancelled();

        $this->assertTrue($result);
        $command->refresh();
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $command->status);
        $this->assertNull($command->idempotency_key);
    }

    public function test_mark_cancelled_fails_on_non_pending_command(): void
    {
        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => '1002', 'Name' => 'Test User'],
            'status' => AdmsCommandStatusEnum::ACKNOWLEDGED,
            'idempotency_key' => "user_add:{$this->device->id}:1002",
        ]);

        $result = $command->markCancelled();

        $this->assertFalse($result);
        $command->refresh();
        $this->assertSame(AdmsCommandStatusEnum::ACKNOWLEDGED, $command->status);
        $this->assertNotNull($command->idempotency_key);
    }

    public function test_cancelling_command_allows_re_enqueuing_with_same_idempotency_key(): void
    {
        $idempotencyKey = "user_add:{$this->device->id}:1003";

        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => '1003', 'Name' => 'Original'],
            'status' => AdmsCommandStatusEnum::PENDING,
            'idempotency_key' => $idempotencyKey,
        ]);

        // Cancelling frees the idempotency key slot
        $command->markCancelled();

        // New command with same idempotency key succeeds
        $newCommand = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => '1003', 'Name' => 'Retry'],
            'status' => AdmsCommandStatusEnum::PENDING,
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->assertIsArray($newCommand->command_payload);
        $this->assertSame('Retry', $newCommand->command_payload['Name'] ?? null);
        $this->assertSame($idempotencyKey, $newCommand->idempotency_key);
    }

    public function test_duplicate_idempotency_key_throws_unique_constraint_violation(): void
    {
        $idempotencyKey = "user_add:{$this->device->id}:1004";

        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => '1004', 'Name' => 'Original'],
            'status' => AdmsCommandStatusEnum::PENDING,
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => '1004', 'Name' => 'Duplicate'],
            'status' => AdmsCommandStatusEnum::PENDING,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function test_mark_cancelled_succeeds_on_sent_command(): void
    {
        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => '1005', 'Name' => 'Sent User'],
            'status' => AdmsCommandStatusEnum::SENT,
            'sent_at' => now()->subMinutes(5),
            'idempotency_key' => "user_add:{$this->device->id}:1005",
        ]);

        $result = $command->markCancelled();

        $this->assertTrue($result);
        $command->refresh();
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $command->status);
        $this->assertNull($command->idempotency_key);
    }

    public function test_cancel_delete_commands_cancels_both_pending_and_sent(): void
    {
        $pending = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => '2001'],
            'status' => AdmsCommandStatusEnum::PENDING,
            'idempotency_key' => "user_delete:{$this->device->id}:2001",
        ]);

        $sent = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => '2002'],
            'status' => AdmsCommandStatusEnum::SENT,
            'sent_at' => now()->subMinutes(10),
            'idempotency_key' => "user_delete:{$this->device->id}:2002",
        ]);

        $count = AdmsDeviceCommand::cancelDeleteCommands($this->device->id, ['2001', '2002']);

        $this->assertSame(2, $count);

        $pending->refresh();
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $pending->status);
        $this->assertNull($pending->idempotency_key);

        $sent->refresh();
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $sent->status);
        $this->assertNull($sent->idempotency_key);
    }

    public function test_mark_acknowledged_preserves_idempotency_key_on_success_and_failure(): void
    {
        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => '3001', 'Name' => 'Acked User'],
            'status' => AdmsCommandStatusEnum::SENT,
            'sent_at' => now()->subMinutes(5),
            'idempotency_key' => "user_add:{$this->device->id}:3001",
        ]);

        $success = $command->markAcknowledged('0');

        $this->assertTrue($success);
        $command->refresh();
        $this->assertSame(AdmsCommandStatusEnum::ACKNOWLEDGED, $command->status);
        $this->assertSame("user_add:{$this->device->id}:3001", $command->idempotency_key);

        // Failure branch preserves idempotency_key for reconciler re-drive tracking
        $failedCommand = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => '3002', 'Name' => 'Failed User'],
            'status' => AdmsCommandStatusEnum::SENT,
            'sent_at' => now()->subMinutes(5),
            'attempt_count' => 1,
            'idempotency_key' => "user_add:{$this->device->id}:3002",
        ]);

        $failSuccess = $failedCommand->markAcknowledged('-1');
        $this->assertTrue($failSuccess);
        $failedCommand->refresh();
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $failedCommand->status);
        $this->assertSame("user_add:{$this->device->id}:3002", $failedCommand->idempotency_key);

        // The rejection is not an additional delivery, so the counter is unchanged.
        $this->assertSame(1, $failedCommand->attempt_count);
    }

    public function test_deleting_pending_template_does_not_cancel_unrelated_member_commands_on_same_device(): void
    {
        $memberA = GymMember::factory()->create(['gym_id' => $this->gym->id]);
        $memberB = GymMember::factory()->create(['gym_id' => $this->gym->id]);

        $templateA = BiometricTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $memberA->id,
            'enrolled_by_device_id' => $this->device->id,
            'provider_type' => 'device_native',
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
            'is_active' => false,
            'template_ref' => null,
        ]);

        $commandA = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $memberA->id, 'Name' => $memberA->name],
            'status' => AdmsCommandStatusEnum::PENDING,
            'idempotency_key' => "user_add:{$this->gym->id}:{$this->device->id}:{$memberA->id}:{$templateA->id}",
        ]);

        // Command for member B on same device
        $commandB = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $memberB->id, 'Name' => $memberB->name],
            'status' => AdmsCommandStatusEnum::PENDING,
            'idempotency_key' => "user_add:{$this->gym->id}:{$this->device->id}:{$memberB->id}:99999",
        ]);

        $deletionService = resolve(BiometricDataDeletionService::class);
        $deletionService->deleteSingleTemplate($templateA);

        $commandA->refresh();
        $this->assertSame(AdmsCommandStatusEnum::CANCELLED, $commandA->status);

        $commandB->refresh();
        $this->assertSame(AdmsCommandStatusEnum::PENDING, $commandB->status);
        $this->assertSame("user_add:{$this->gym->id}:{$this->device->id}:{$memberB->id}:99999", $commandB->idempotency_key);
    }
}
