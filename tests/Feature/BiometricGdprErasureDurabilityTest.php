<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\BiometricDataDeletionService;
use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricConsent;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BiometricGdprErasureDurabilityTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected GymMember $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'device_type' => 'fingerprint',
        ]);

        $this->member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
        ]);

        BiometricConsent::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'consented_at' => now(),
            'ip_address' => '127.0.0.1',
            'version' => '1.0',
            'revoked_at' => null,
        ]);
    }

    #[Test]
    public function was_delivered_to_hardware_returns_true_when_hardware_confirmed_at_is_set_even_if_commands_are_pruned(): void
    {
        $template = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now()->subMonths(6),
            'hardware_confirmed_at' => now()->subMonths(6),
        ]);

        // Ensure zero AdmsDeviceCommands in table (simulating routine command log pruning)
        AdmsDeviceCommand::query()->delete();
        $this->assertSame(0, AdmsDeviceCommand::query()->count());

        $this->assertTrue($template->wasDeliveredToHardware());

        // Deleting this template must still queue a user_delete command to hardware
        $deletionService = resolve(BiometricDataDeletionService::class);
        $result = $deletionService->deleteSingleTemplate($template);

        $this->assertTrue($result);

        $deleteCommand = AdmsDeviceCommand::query()
            ->where('device_id', $this->device->id)
            ->where('command_type', 'user_delete')
            ->first();

        $this->assertNotNull($deleteCommand);
        $this->assertSame((string) $this->member->id, $deleteCommand->command_payload['Pin'] ?? null);
    }

    #[Test]
    public function was_delivered_to_hardware_falls_back_to_the_command_log_when_the_column_is_null(): void
    {
        Log::shouldReceive('warning')->never();
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $template = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => true,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'enrolled_at' => now()->subMonths(1),
            'hardware_confirmed_at' => null, // Legacy unbackfilled record
        ]);

        // Create an acknowledged user_add command for this template's own enrolment
        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'status' => AdmsCommandStatusEnum::ACKNOWLEDGED,
            'acknowledged_at' => now()->subMonths(1),
            'idempotency_key' => AdmsCommandKey::buildUserAdd(
                $this->gym->id,
                $this->device->id,
                $this->member->id,
                $template->id
            ),
        ]);

        $this->assertTrue($template->wasDeliveredToHardware());
    }

    #[Test]
    public function was_delivered_to_hardware_returns_false_when_unconfirmed_and_no_commands_exist(): void
    {
        $template = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
            'enrolled_at' => null,
            'hardware_confirmed_at' => null,
        ]);

        $this->assertFalse($template->wasDeliveredToHardware());

        // Deleting this template transitions to Deleted immediately without queuing hardware command
        $deletionService = resolve(BiometricDataDeletionService::class);
        $result = $deletionService->deleteSingleTemplate($template);

        $this->assertTrue($result);
        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $template->sync_status);
        $this->assertFalse($template->is_active);
        $this->assertSame(0, AdmsDeviceCommand::query()->where('command_type', 'user_delete')->count());
    }

    #[Test]
    public function reconcile_user_add_sets_hardware_confirmed_at_on_successful_acknowledgment(): void
    {
        $template = BiometricTemplate::query()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->device->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'provider_type' => 'device_native',
            'template_ref' => (string) $this->member->id,
            'is_active' => false,
            'sync_status' => BiometricTemplateSyncStatusEnum::Propagated,
            'enrolled_at' => null,
            'hardware_confirmed_at' => null,
        ]);

        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_add',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'status' => AdmsCommandStatusEnum::SENT,
        ]);

        $command->markAcknowledged('0');

        $template->refresh();
        $this->assertNotNull($template->hardware_confirmed_at);
        $this->assertNotNull($template->enrolled_at);
    }
}
