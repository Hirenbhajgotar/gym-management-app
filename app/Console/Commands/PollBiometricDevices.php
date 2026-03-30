<?php

namespace App\Console\Commands;

use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\Services\CheckInService;
use App\Events\DoorControllerUnreachable;
use App\Models\BiometricDevice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PollBiometricDevices extends Command
{
    protected $signature = 'biometric:poll';

    protected $description = 'Poll biometric devices that do not support HTTP push';

    public function handle(
        BiometricProviderRegistry $registry,
        CheckInService $checkInService
    ): int {
        $this->info('Starting biometric device polling...');

        $devices = BiometricDevice::where('status', 'active')
            ->where('connection_mode', 'poll')
            ->get();

        if ($devices->isEmpty()) {
            $this->info('No poll-mode devices found.');

            return Command::SUCCESS;
        }

        $this->info("Found {$devices->count()} device(s) to poll.");

        foreach ($devices as $device) {
            $this->processDevice($registry, $checkInService, $device);
        }

        $this->info('Polling complete.');

        return Command::SUCCESS;
    }

    protected function processDevice(
        BiometricProviderRegistry $registry,
        CheckInService $checkInService,
        BiometricDevice $device
    ): void {
        $this->line("Polling device: {$device->device_name} (ID: {$device->id})");

        try {
            // Poll mode requires the device to have a local API that FluxFitness can query
            // This is different from webhook mode where device pushes events to FluxFitness
            // For poll mode to work, the device needs to implement a getEvents() endpoint
            // that returns pending biometric events since last poll

            // Resolve the actual provider instance from the registry
            $provider = $registry->resolve($device->provider_type, $device->provider_config ?? []);

            // Check if the resolved provider supports fetching events
            if (! method_exists($provider, 'fetchRecentEvents')) {
                $this->warn('  Provider does not support event fetching. Poll mode requires custom provider implementation.');

                return;
            }

            // Note: fetchRecentEvents and processWebhookEvent methods need to be added
            // to BiometricProvider interface and implemented in providers for full polling support
            // For now, this is a placeholder that logs the need for implementation

            $this->warn('  Polling is not yet fully implemented for this provider type.');
            $this->line('  To enable polling, the biometric provider must implement fetchRecentEvents() method.');

            // Update last_polled_at timestamp anyway to show the device was checked
            $device->update(['last_polled_at' => now()]);

        } catch (\Exception $e) {
            $this->error("  Polling failed: {$e->getMessage()}");
            Log::error("Poll failed for device {$device->id}", [
                'error' => $e->getMessage(),
            ]);

            // Fire door controller unreachable event for API-based door control
            if ($device->isDoorAccess() && $device->hasDoorApiControl()) {
                event(new DoorControllerUnreachable($device, $e->getMessage()));
            }
        }
    }
}
