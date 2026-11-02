<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `where('enrolled_by_device_id', $this->device_id)` compiles to `IS NULL` when the command's
 * terminal was deleted, matching every detached hardware template in the gym that shares the
 * PIN. `resolveTargetMember()` already refuses that shape; the two reconciliation paths must
 * refuse it too, because the changeset guarantees null-device rows exist.
 */
class AdmsNullDeviceReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected GymMember $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->member = GymMember::factory()->create(['gym_id' => $this->gym->id]);
    }

    #[Test]
    public function a_null_device_user_delete_ack_does_not_converge_detached_templates(): void
    {
        $detached = BiometricTemplate::factory()->deviceNative()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => null,
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        $command = $this->orphanedCommand('user_delete');

        $this->assertTrue($command->markAcknowledged('0'));

        $this->assertSame(
            BiometricTemplateSyncStatusEnum::PendingDeletion,
            $detached->refresh()->sync_status,
            'A command with no terminal proves nothing about a detached credential.'
        );
    }

    #[Test]
    public function a_null_device_user_add_ack_does_not_promote_detached_templates(): void
    {
        $detached = BiometricTemplate::factory()->deviceNative()->pending()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => null,
        ]);

        $command = $this->orphanedCommand('user_add');

        $this->assertTrue($command->markAcknowledged('0'));

        $fresh = $detached->refresh();

        $this->assertSame(BiometricTemplateSyncStatusEnum::Pending, $fresh->sync_status);
        $this->assertFalse((bool) $fresh->is_active);
        $this->assertNull($fresh->hardware_confirmed_at);
    }

    private function orphanedCommand(string $type): AdmsDeviceCommand
    {
        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => null,
            'command_type' => $type,
            'command_payload' => ['Pin' => (string) $this->member->id],
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        // Move it to SENT without a lease: this is the state a terminal leaves behind when it
        // dequeues a command and then disappears before acknowledging it.
        $command->forceFill(['status' => AdmsCommandStatusEnum::SENT])->saveQuietly();

        return $command;
    }
}
