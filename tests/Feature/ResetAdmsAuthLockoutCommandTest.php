<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\AdmsDeviceAuthenticator;
use App\Models\BiometricDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class ResetAdmsAuthLockoutCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_serial_selector_unlocks_an_administratively_disabled_device(): void
    {
        $device = BiometricDevice::factory()
            ->admsEnabled('SN-DISABLED-LOCKOUT')
            ->administrativelyDisabled()
            ->create();

        /** @var AdmsDeviceAuthenticator $authenticator */
        $authenticator = resolve(AdmsDeviceAuthenticator::class);

        Cache::put(AdmsDeviceAuthenticator::LOCKOUT_PREFIX . $device->id, true, now()->addMinutes(15));

        $this->artisan('biometrics:reset-adms-lockout', ['--serial' => 'SN-DISABLED-LOCKOUT'])
            ->expectsOutputToContain('Cleared ADMS authentication lockout')
            ->assertSuccessful();

        $this->assertFalse($authenticator->isLockedOut($device->refresh()));
    }

    public function test_command_reports_failure_when_the_cache_cannot_be_cleared(): void
    {
        BiometricDevice::factory()
            ->admsEnabled('SN-CACHE-FAIL')
            ->create();

        Cache::shouldReceive('forget')->andThrow(new RuntimeException('cache down'));
        Cache::shouldReceive('flush')->andReturnTrue();

        $this->artisan('biometrics:reset-adms-lockout', ['--serial' => 'SN-CACHE-FAIL'])
            ->expectsOutputToContain('cache store is unavailable')
            ->assertFailed();
    }
}
