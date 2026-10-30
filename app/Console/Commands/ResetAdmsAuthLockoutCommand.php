<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Biometrics\Services\AdmsDeviceAuthenticator;
use App\Models\BiometricDevice;
use Illuminate\Console\Command;

class ResetAdmsAuthLockoutCommand extends Command
{
    protected $signature = 'biometrics:reset-adms-lockout
        {--device-id= : Device ID to unlock}
        {--serial= : Device serial to unlock}
        {--all : Unlock every ADMS-enabled device}';

    protected $description = 'Clear the per-device ADMS authentication failure counter and lockout';

    public function handle(AdmsDeviceAuthenticator $authenticator): int
    {
        $deviceId = $this->option('device-id');
        $serial = $this->option('serial');
        $all = $this->option('all');

        if (! $all && blank($deviceId) && blank($serial)) {
            $this->error('Provide --device-id, --serial, or --all.');

            return self::FAILURE;
        }

        if (filled($deviceId)) {
            $validated = filter_var($deviceId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($validated === false) {
                $this->error('--device-id must be a positive integer.');

                return self::FAILURE;
            }

            $device = BiometricDevice::query()->find($validated);

            if (! $device) {
                $this->error('Device not found.');

                return self::FAILURE;
            }

            if (! $authenticator->resetFailures($device)) {
                $this->error("Could not clear the ADMS authentication lockout for device [ID: {$device->id}]; the cache store is unavailable.");

                return self::FAILURE;
            }

            $this->info("Cleared ADMS authentication lockout for device [ID: {$device->id}].");

            return self::SUCCESS;
        }

        if (filled($serial)) {
            // Deliberately bypass findBySerial(): it filters disabled devices,
            // and an administratively disabled terminal must still be unlockable
            // before it is re-enabled.
            $device = BiometricDevice::query()
                ->where('device_serial', $serial)
                ->first();

            if (! $device) {
                $this->error('No device found for that serial.');

                return self::FAILURE;
            }

            if (! $authenticator->resetFailures($device)) {
                $this->error("Could not clear the ADMS authentication lockout for device [ID: {$device->id}]; the cache store is unavailable.");

                return self::FAILURE;
            }

            $this->info("Cleared ADMS authentication lockout for device [ID: {$device->id}].");

            return self::SUCCESS;
        }

        $count = 0;
        $failed = 0;

        // Include disabled devices: a stale lockout would otherwise reappear
        // as soon as the device is re-enabled.
        BiometricDevice::query()
            ->chunkById(100, function ($devices) use ($authenticator, &$count, &$failed): void {
                foreach ($devices as $device) {
                    if ($authenticator->resetFailures($device)) {
                        $count++;
                    } else {
                        $failed++;
                    }
                }
            });

        if ($failed > 0) {
            $this->error("Cleared ADMS authentication lockout state for {$count} device(s); {$failed} device(s) could not be cleared because the cache store is unavailable.");

            return self::FAILURE;
        }

        $this->info("Cleared ADMS authentication lockout state for {$count} device(s).");

        return self::SUCCESS;
    }
}
