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

class AdmsQueryUserCommandTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();

        $this->device = BiometricDevice::factory()
            ->admsEnabled('SN-QUERY-TEST-001')
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'purpose' => BiometricDevicePurpose::AttendanceOnly,
            ]);
    }

    #[Test]
    public function it_fails_when_neither_device_id_nor_serial_is_provided(): void
    {
        $this->artisan('biometrics:query-user')
            ->expectsOutput('Provide --device-id or --serial.')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_queues_user_query_command_with_specific_pin(): void
    {
        $this->artisan('biometrics:query-user', [
            '--device-id' => $this->device->id,
            '--pin' => '369',
        ])
            ->expectsOutputToContain('Queued user_query command')
            ->assertExitCode(0);

        $this->assertDatabaseHas('adms_device_commands', [
            'device_id' => $this->device->id,
            'gym_id' => $this->gym->id,
            'command_type' => 'user_query',
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);

        $command = AdmsDeviceCommand::query()
            ->where('device_id', $this->device->id)
            ->where('command_type', 'user_query')
            ->first();

        $this->assertNotNull($command);
        $this->assertSame('369', $command->command_payload['Pin'] ?? null);
        $this->assertSame("C:{$command->id}:DATA QUERY USERINFO PIN=369", $command->toAdmsCommandString());
    }

    #[Test]
    public function it_queues_query_all_users_command_when_pin_is_omitted(): void
    {
        $this->artisan('biometrics:query-user', [
            '--serial' => 'SN-QUERY-TEST-001',
        ])
            ->expectsOutputToContain('Queued query_all_users command')
            ->assertExitCode(0);

        $this->assertDatabaseHas('adms_device_commands', [
            'device_id' => $this->device->id,
            'gym_id' => $this->gym->id,
            'command_type' => 'query_all_users',
            'status' => AdmsCommandStatusEnum::PENDING->value,
        ]);

        $command = AdmsDeviceCommand::query()
            ->where('device_id', $this->device->id)
            ->where('command_type', 'query_all_users')
            ->first();

        $this->assertNotNull($command);
        $this->assertSame("C:{$command->id}:DATA QUERY USERINFO", $command->toAdmsCommandString());
    }

    #[Test]
    public function it_fails_when_device_id_is_not_a_positive_integer(): void
    {
        $this->artisan('biometrics:query-user', ['--device-id' => 'not-an-int'])
            ->expectsOutput('--device-id must be a positive integer.')
            ->assertExitCode(1);

        $this->artisan('biometrics:query-user', ['--device-id' => '-5'])
            ->expectsOutput('--device-id must be a positive integer.')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_fails_when_device_is_not_found(): void
    {
        $this->artisan('biometrics:query-user', ['--device-id' => '999999'])
            ->expectsOutput('Device not found.')
            ->assertExitCode(1);

        $this->artisan('biometrics:query-user', ['--serial' => 'NON-EXISTENT-SN'])
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

        $this->artisan('biometrics:query-user', ['--device-id' => $nonAdmsDevice->id])
            ->expectsOutput("Device [ID: {$nonAdmsDevice->id}] is not configured for ADMS protocol.")
            ->assertExitCode(1);
    }

    #[Test]
    public function it_fails_when_pin_contains_control_characters(): void
    {
        $this->artisan('biometrics:query-user', [
            '--device-id' => $this->device->id,
            '--pin' => "369\tPri=1",
        ])
            ->expectsOutput("PIN [369\tPri=1] contains invalid characters or is unaddressable.")
            ->assertExitCode(1);
    }

    #[Test]
    public function polled_query_command_is_delivered_with_crlf_terminator(): void
    {
        $this->artisan('biometrics:query-user', [
            '--device-id' => $this->device->id,
            '--pin' => '369',
        ])->assertExitCode(0);

        config(['biometrics.adms.require_comm_key' => false]);
        $response = $this->get("/api/iclock/getrequest?SN={$this->device->device_serial}");

        $response->assertOk();
        $content = (string) $response->getContent();
        $this->assertStringEndsWith("\r\n", $content);
        $this->assertStringContainsString('DATA QUERY USERINFO PIN=369', $content);
    }
}
