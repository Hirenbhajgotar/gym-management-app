<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\DefaultRole;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmsCommandAckCasTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->gym = Gym::factory()
            ->hasAttached($this->user, ['role' => DefaultRole::GYM_OWNER->value], 'users')
            ->create();

        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'is_enabled' => true,
            'device_serial' => 'SN-ACK-TEST-01',
            'adms_enabled' => true,
            'provider_type' => null,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'provider_config' => ['pushcommkey' => 'commkey123'],
        ]);
    }

    public function test_only_sent_command_can_be_acknowledged_via_cas(): void
    {
        /** @var AdmsDeviceCommand $command */
        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'reboot',
            'status' => AdmsCommandStatusEnum::PENDING,
            'attempt_count' => 0,
        ]);

        // Attempting to acknowledge a PENDING command directly must be rejected by CAS
        $ackResult = $command->markAcknowledged('0');
        $this->assertFalse($ackResult);
        $this->assertEquals(AdmsCommandStatusEnum::PENDING, $command->refresh()->status);

        // Mark sent
        $sentResult = $command->markSent(600);
        $this->assertTrue($sentResult);
        $this->assertEquals(AdmsCommandStatusEnum::SENT, $command->refresh()->status);

        // Now acknowledge should succeed
        $ackSuccess = $command->markAcknowledged('0');
        $this->assertTrue($ackSuccess);
        $this->assertEquals(AdmsCommandStatusEnum::ACKNOWLEDGED, $command->refresh()->status);

        // Re-acknowledging should fail (cannot transition from ACKNOWLEDGED to ACKNOWLEDGED)
        $reAckResult = $command->markAcknowledged('0');
        $this->assertFalse($reAckResult);
    }

    public function test_http_device_cmd_rejects_acknowledging_pending_command(): void
    {
        /** @var AdmsDeviceCommand $command */
        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'INFO',
            'status' => AdmsCommandStatusEnum::PENDING,
            'attempt_count' => 0,
        ]);

        // Device POSTs ACK for a command that was never marked SENT
        $response = $this->call(
            'POST',
            '/api/iclock/devicecmd?SN=SN-ACK-TEST-01&pushcommkey=commkey123',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            "ID={$command->id}&Return=0"
        );

        $response->assertOk();

        // Command status should remain PENDING because it was never leased/sent
        $this->assertEquals(AdmsCommandStatusEnum::PENDING, $command->refresh()->status);
    }
}
