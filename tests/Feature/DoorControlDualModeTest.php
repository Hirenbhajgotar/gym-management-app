<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Contracts\HostResolverInterface;
use App\Biometrics\Services\DoorControlService;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Models\BiometricDevice;
use App\Models\Gym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DoorControlDualModeTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();

        $mockResolver = new class() implements HostResolverInterface
        {
            public function resolve(string $host): ?string
            {
                if (str_contains(strtolower($host), 'door-controller.internal')) {
                    return '203.0.113.20';
                }

                return null;
            }
        };
        $this->app->instance(HostResolverInterface::class, $mockResolver);
    }

    public function test_native_relay_device_returns_skipped(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'purpose' => BiometricDevicePurpose::DoorAccess,
            'status' => BiometricDeviceStatusEnum::Active,
            'provider_config' => [],
        ]);

        /** @var DoorControlService $service */
        $service = resolve(DoorControlService::class);
        $result = $service->unlock($device);

        $this->assertTrue($result->success);
        $this->assertSame('skipped', $result->status);
    }

    public function test_non_door_purpose_returns_not_applicable(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'status' => BiometricDeviceStatusEnum::Active,
        ]);

        /** @var DoorControlService $service */
        $service = resolve(DoorControlService::class);
        $result = $service->unlock($device);

        $this->assertTrue($result->success);
        $this->assertSame('not_applicable', $result->status);
    }

    public function test_external_http_controller_device_calls_api_and_returns_unlocked(): void
    {
        Config::set('biometrics.trusted_domains', ['door-controller.internal']);

        Http::fake([
            'http://door-controller.internal/unlock' => Http::response([
                'status' => 'success',
                'unlocked_at' => now()->toIso8601String(),
            ], 200),
        ]);

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'purpose' => BiometricDevicePurpose::DoorAccess,
            'status' => BiometricDeviceStatusEnum::Active,
            'provider_config' => [
                'door_controller_url' => 'http://door-controller.internal/unlock',
                'door_controller_token' => 'secret-relay-token',
                'door_zone' => 'front_turnstile',
            ],
        ]);

        /** @var DoorControlService $service */
        $service = resolve(DoorControlService::class);
        $result = $service->unlock($device);

        $this->assertTrue($result->success);
        $this->assertSame('unlocked', $result->status);
        $this->assertNotNull($result->unlockedAt);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://door-controller.internal/unlock'
                && $request->hasHeader('Authorization', 'Bearer secret-relay-token')
                && $request['action'] === 'unlock'
                && $request['zone'] === 'front_turnstile';
        });
    }

    public function test_external_http_controller_rejects_untrusted_domain(): void
    {
        Config::set('biometrics.trusted_domains', ['trusted.internal']);

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'purpose' => BiometricDevicePurpose::DoorAccess,
            'status' => BiometricDeviceStatusEnum::Active,
            'provider_config' => [
                'door_controller_url' => 'http://evil-external-host.com/unlock',
            ],
        ]);

        /** @var DoorControlService $service */
        $service = resolve(DoorControlService::class);
        $result = $service->unlock($device);

        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        $this->assertSame('untrusted_door_controller_url', $result->failureReason);
    }
}
