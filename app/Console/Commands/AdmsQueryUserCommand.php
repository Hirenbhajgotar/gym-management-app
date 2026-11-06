<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AdmsCommandStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use Illuminate\Console\Command;

class AdmsQueryUserCommand extends Command
{
    protected $signature = 'biometrics:query-user
        {--device-id= : Device ID to query}
        {--serial= : Device serial to query}
        {--pin= : Member PIN to query (omit to query all users on terminal)}';

    protected $description = 'Queue a DATA QUERY USERINFO command for an ADMS biometric device to inspect its internal user roster';

    public function handle(): int
    {
        $deviceId = $this->option('device-id');
        $serial = $this->option('serial');
        $pin = $this->option('pin');

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
        } else {
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

        if (filled($pin)) {
            $pinStr = (string) $pin;

            if (! AdmsDeviceCommand::pinIsUsable($pinStr)) {
                $this->error("PIN [{$pinStr}] contains invalid characters or is unaddressable.");

                return self::FAILURE;
            }

            $command = AdmsDeviceCommand::query()->create([
                'gym_id' => $device->gym_id,
                'device_id' => $device->id,
                'device_serial' => $device->device_serial,
                'command_type' => 'user_query',
                'command_payload' => ['Pin' => $pinStr],
                'status' => AdmsCommandStatusEnum::PENDING,
                'idempotency_key' => sprintf('user_query:%d:%d:%s:%d', $device->gym_id, $device->id, $pinStr, time()),
            ]);

            $this->info("Queued user_query command [ID: {$command->id}] for device [ID: {$device->id}, SN: {$device->device_serial}].");
            $this->line("Wire Command: {$command->toAdmsCommandString()}");
            $this->line('The device will receive this on its next poll (GET /iclock/getrequest).');
            $this->line('Device will upload user data to POST /iclock/cdata?table=USERINFO.');

            return self::SUCCESS;
        }

        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $device->gym_id,
            'device_id' => $device->id,
            'device_serial' => $device->device_serial,
            'command_type' => 'query_all_users',
            'command_payload' => null,
            'status' => AdmsCommandStatusEnum::PENDING,
            'idempotency_key' => sprintf('query_all_users:%d:%d:%d', $device->gym_id, $device->id, time()),
        ]);

        $this->info("Queued query_all_users command [ID: {$command->id}] for device [ID: {$device->id}, SN: {$device->device_serial}].");
        $this->line("Wire Command: {$command->toAdmsCommandString()}");
        $this->line('The device will receive this on its next poll (GET /iclock/getrequest).');

        return self::SUCCESS;
    }
}
