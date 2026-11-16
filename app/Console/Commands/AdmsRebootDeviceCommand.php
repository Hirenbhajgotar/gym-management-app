<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AdmsCommandStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AdmsRebootDeviceCommand extends Command
{
    protected $signature = 'biometrics:reboot-device
        {--device-id= : Device ID to reboot}
        {--serial= : Device serial to reboot}';

    protected $description = 'Queue a REBOOT command for an ADMS biometric device to reload its firmware memory and display';

    public function handle(): int
    {
        $deviceId = $this->option('device-id');
        $serial = $this->option('serial');

        if (blank($deviceId) && blank($serial)) {
            $this->error('Provide --device-id or --serial.');

            return self::FAILURE;
        }

        $query = BiometricDevice::query();

        if (filled($deviceId)) {
            $validated = filter_var($deviceId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($validated === false) {
                $this->error('--device-id must be a positive integer.');

                return self::FAILURE;
            }

            $query->where('id', $validated);
        }

        if (filled($serial)) {
            $query->where('device_serial', $serial);
        }

        /** @var BiometricDevice|null $device */
        $device = $query->first();

        if (! $device) {
            $this->error('Device not found.');

            return self::FAILURE;
        }

        if (! $device->adms_enabled) {
            $this->error("Device [ID: {$device->id}] is not configured for ADMS protocol.");

            return self::FAILURE;
        }

        $inFlight = AdmsDeviceCommand::query()
            ->where('device_id', $device->id)
            ->where('command_type', 'reboot')
            ->whereIn('status', [AdmsCommandStatusEnum::PENDING, AdmsCommandStatusEnum::SENT])
            ->first();

        if ($inFlight) {
            $this->warn("A reboot command [ID: {$inFlight->id}, Status: {$inFlight->status->value}] is already in-flight for this device.");

            return self::SUCCESS;
        }

        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $device->gym_id,
            'device_id' => $device->id,
            'device_serial' => $device->device_serial,
            'command_type' => 'reboot',
            'command_payload' => null,
            'status' => AdmsCommandStatusEnum::PENDING,
            'idempotency_key' => sprintf('reboot:%d:%d:%d:%s', $device->gym_id, $device->id, time(), Str::random(6)),
        ]);

        $this->info("Queued reboot command [ID: {$command->id}] for device [ID: {$device->id}, SN: {$device->device_serial}].");
        $this->line("Wire Command: {$command->toAdmsCommandString()}");
        $this->line('The device will receive this on its next poll (GET /iclock/getrequest) and restart.');

        return self::SUCCESS;
    }
}
