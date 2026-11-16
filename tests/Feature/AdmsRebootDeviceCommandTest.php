<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\Gym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdmsRebootDeviceCommandTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();

        $this->device = BiometricDevice::factory()
            ->admsEnabled('SN-REBOOT-TEST-001')
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'purpose' => BiometricDevicePurpose::AttendanceOnly,
            ]);
    }

    #[Test]
    public function it_fails_when_neither_device_id_nor_serial_is_provided(): void
    {
        $this->artisan('biometrics:reboot-device')
            ->expectsOutput('Provide --device-id or --serial.')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_fails_when_device_id_is_non_integer(): void
    {
        $this->artisan('biometrics:reboot-device', ['--device-id' => 'abc'])
            ->expectsOutput('--device-id must be a positive integer.')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_fails_when_device_is_not_found(): void
    {
        $this->artisan('biometrics:reboot-device', ['--device-id' => '99999'])
            ->expectsOutput('Device not found.')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_fails_when_device_is_not_adms_enabled(): void
    {
        $nonAdmsDevice = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'adms_enabled' => false,
        ]);

        $this->artisan('biometrics:reboot-device', ['--device-id' => (string) $nonAdmsDevice->id])
            ->expectsOutput("Device [ID: {$nonAdmsDevice->id}] is not configured for ADMS protocol.")
            ->assertExitCode(1);
    }

    #[Test]
    public function it_queues_reboot_command_by_device_id(): void
    {
        $this->artisan('biometrics:reboot-device', [
            '--device-id' => $this->device->id,
        ])
            ->expectsOutputToContain('Queued reboot command')
            ->assertExitCode(0);

        $this->assertDatabaseHas('adms_device_commands', [
            'device_id' => $this->device->id,
            'gym_id' => $this->gym->id,
            'command_type' => 'reboot',
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);

        $command = AdmsDeviceCommand::query()
            ->where('device_id', $this->device->id)
            ->where('command_type', 'reboot')
            ->first();

        $this->assertNotNull($command);
        $this->assertSame("C:{$command->id}:Restart", $command->toAdmsCommandString());
    }

    #[Test]
    public function it_queues_reboot_command_by_serial(): void
    {
        $this->artisan('biometrics:reboot-device', [
            '--serial' => 'SN-REBOOT-TEST-001',
        ])
            ->expectsOutputToContain('Queued reboot command')
            ->assertExitCode(0);

        $this->assertDatabaseHas('adms_device_commands', [
            'device_id' => $this->device->id,
            'gym_id' => $this->gym->id,
            'command_type' => 'reboot',
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);
    }

    #[Test]
    public function it_fails_when_both_device_id_and_serial_are_passed_and_mismatched(): void
    {
        $this->artisan('biometrics:reboot-device', [
            '--device-id' => $this->device->id,
            '--serial' => 'NON-MATCHING-SERIAL',
        ])
            ->expectsOutput('Device not found.')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_warns_and_does_not_queue_when_reboot_command_is_already_in_flight(): void
    {
        AdmsDeviceCommand::factory()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'device_serial' => $this->device->device_serial,
            'command_type' => 'reboot',
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        $this->artisan('biometrics:reboot-device', [
            '--device-id' => $this->device->id,
        ])
            ->expectsOutputToContain('already in-flight')
            ->assertExitCode(0);

        $this->assertDatabaseCount('adms_device_commands', 1);
    }

    #[Test]
    public function polled_reboot_command_is_delivered_with_crlf_terminator(): void
    {
        $this->artisan('biometrics:reboot-device', [
            '--device-id' => $this->device->id,
        ])->assertExitCode(0);

        config(['biometrics.adms.require_comm_key' => false]);
        $response = $this->get("/api/iclock/getrequest?SN={$this->device->device_serial}");

        $response->assertOk();
        $content = (string) $response->getContent();
        $this->assertStringEndsWith("\r\n", $content);
        $this->assertStringContainsString(':Restart', $content);
    }
}
