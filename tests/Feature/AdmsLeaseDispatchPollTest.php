<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\ValueObjects\DeliveryLease;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\DefaultRole;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\ReusesMigratedTestDatabase;
use Tests\Concerns\SkipsFilamentBoot;
use Tests\TestCase;

/**
 * The lease-and-dispatch poll path: one call yields a leasable command plus a
 * ready wire, and the controller returns that wire verbatim.
 */
final class AdmsLeaseDispatchPollTest extends TestCase
{
    use ReusesMigratedTestDatabase;
    use SkipsFilamentBoot;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        // Must precede parent::setUp(): that is where the application is created.
        $this->skipFilamentBoot();

        parent::setUp();

        config(['biometrics.adms.require_comm_key' => false]);

        $user = User::factory()->create();
        $this->gym = Gym::factory()
            ->hasAttached($user, ['role' => DefaultRole::GYM_OWNER->value], 'users')
            ->create();

        $this->device = BiometricDevice::factory()
            ->admsEnabled('LEASE-DISPATCH-SN')
            ->create([
                'gym_id' => $this->gym->id,
                'status' => BiometricDeviceStatusEnum::Active,
                'purpose' => BiometricDevicePurpose::AttendanceOnly,
            ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->restoreFilamentBoot();
    }

    public function test_lease_next_for_delivery_returns_a_ready_crlf_terminated_wire(): void
    {
        $command = AdmsDeviceCommand::factory()->userDelete('123')->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
        ]);

        $lease = AdmsDeviceCommand::leaseNextForDelivery($this->device->id);

        $this->assertInstanceOf(DeliveryLease::class, $lease);
        $this->assertSame($command->id, $lease->command->id);
        // Independent literal: the wire is serialised and CRLF-terminated by the seam.
        $this->assertSame(sprintf('C:%d:DATA DELETE USERINFO PIN=123', $command->id) . "\r\n", $lease->wire);
        $this->assertSame(AdmsCommandStatusEnum::SENT, $command->fresh()?->status);
    }

    public function test_lease_next_for_delivery_returns_null_when_nothing_is_leasable(): void
    {
        $this->assertNull(AdmsDeviceCommand::leaseNextForDelivery($this->device->id));
    }

    public function test_lease_next_for_delivery_quarantines_an_unusable_pin_without_consuming_an_attempt(): void
    {
        $command = AdmsDeviceCommand::factory()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => "12\t3"],
            'status' => AdmsCommandStatusEnum::PENDING,
        ]);

        $this->assertNull(AdmsDeviceCommand::leaseNextForDelivery($this->device->id));

        $fresh = $command->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(AdmsCommandStatusEnum::FAILED, $fresh->status);
        $this->assertSame(AdmsDeviceCommand::UNADDRESSABLE_PIN_CODE, $fresh->device_return_code);
        $this->assertSame(0, $fresh->attempt_count);
    }

    public function test_getrequest_returns_the_leased_wire_verbatim(): void
    {
        $command = AdmsDeviceCommand::factory()->userDelete('456')->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
        ]);

        $response = $this->get('/api/iclock/getrequest?SN=LEASE-DISPATCH-SN');

        $response->assertOk();
        $this->assertSame(
            sprintf('C:%d:DATA DELETE USERINFO PIN=456', $command->id) . "\r\n",
            (string) $response->getContent()
        );
        $this->assertSame(AdmsCommandStatusEnum::SENT, $command->fresh()?->status);
    }

    public function test_getrequest_replies_ok_when_the_delivery_lease_is_null(): void
    {
        $response = $this->get('/api/iclock/getrequest?SN=LEASE-DISPATCH-SN');

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());
    }

    public function test_the_poll_path_never_writes_the_wire_payload_to_logs(): void
    {
        $command = AdmsDeviceCommand::factory()->userDelete('789')->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->device->id,
        ]);
        $wireBody = sprintf('C:%d:DATA DELETE USERINFO PIN=789', $command->id);

        Log::spy();
        Log::shouldNotReceive('info')->withArgs(
            fn (mixed $message): bool => is_string($message) && str_contains($message, $wireBody)
        );
        Log::shouldNotReceive('warning')->withArgs(
            fn (mixed $message): bool => is_string($message) && str_contains($message, $wireBody)
        );

        $response = $this->get('/api/iclock/getrequest?SN=LEASE-DISPATCH-SN');

        $response->assertOk();
        $this->assertStringContainsString($wireBody, (string) $response->getContent());
    }
}
