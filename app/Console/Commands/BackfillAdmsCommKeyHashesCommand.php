<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BiometricDevice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillAdmsCommKeyHashesCommand extends Command
{
    protected $signature = 'biometrics:backfill-adms-comm-keys
        {--dry-run : Preview changes without updating the database}
        {--provision : Generate and provision 6-digit numeric keys for ADMS devices lacking a comm key}
        {--gym-id= : Scope backfill to a specific gym ID}
        {--device-id= : Scope backfill to a specific device ID}';

    protected $description = 'Migrate plaintext ADMS pushcommkeys from provider_config to adms_comm_key_hash column and provision missing keys';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $provision = $this->option('provision');
        $gymId = $this->option('gym-id');
        $deviceId = $this->option('device-id');

        $migrated = 0;
        $refused = 0;
        $provisioned = 0;
        /** @var array<int, BiometricDevice> $unkeyedDevices */
        $unkeyedDevices = [];
        $provisionedTable = [];

        // 1. Process devices that currently have provider_config and null adms_comm_key_hash
        $query1 = BiometricDevice::query()
            ->whereNull('adms_comm_key_hash')
            ->whereNotNull('provider_config');

        if (filled($gymId)) {
            $query1->where('gym_id', (int) $gymId);
        }

        if (filled($deviceId)) {
            $query1->where('id', (int) $deviceId);
        }

        $query1->chunkById(100, function ($devices) use ($dryRun, &$migrated, &$refused, &$unkeyedDevices): void {
            foreach ($devices as $device) {
                $config = is_array($device->provider_config) ? $device->provider_config : [];
                $rawKey = $config['pushcommkey'] ?? $config['comm_key'] ?? null;

                if (blank($rawKey)) {
                    if ($device->adms_enabled) {
                        $unkeyedDevices[$device->id] = $device;
                    }

                    continue;
                }

                // Refuse numeric keys stored as non-strings (e.g. JSON numbers that lost leading zeros or precision)
                if (! is_string($rawKey)) {
                    $this->warn("Device [ID: {$device->id}, SN: {$device->device_serial}] has a non-string numeric comm key in provider_config. Refused migration to prevent bricking device. Please re-enter key as string or re-provision.");
                    $refused++;

                    continue;
                }

                $salt = BiometricDevice::generateCommKeySalt();
                $hash = BiometricDevice::hashCommKey($rawKey, $salt);

                if (! $dryRun) {
                    DB::transaction(function () use ($device, $config, $hash, $salt): void {
                        unset($config['pushcommkey'], $config['comm_key']);
                        $device->adms_comm_key_hash = $hash;
                        $device->adms_comm_key_salt = $salt;
                        $device->provider_config = $config;
                        $device->save();
                    });

                    activity()
                        ->performedOn($device)
                        ->withProperties([
                            'gym_id' => $device->gym_id,
                            'action' => 'adms_comm_key_migrated',
                        ])
                        ->log('ADMS device comm key migrated to PBKDF2 hash');
                }

                $this->info("Device [ID: {$device->id}, SN: {$device->device_serial}] migrated pushcommkey hash.");
                $migrated++;
            }
        });

        // 2. Identify remaining adms_enabled devices with null adms_comm_key_hash and null provider_config
        $query2 = BiometricDevice::query()
            ->where('adms_enabled', true)
            ->whereNull('adms_comm_key_hash')
            ->whereNull('provider_config');

        if (filled($gymId)) {
            $query2->where('gym_id', (int) $gymId);
        }

        if (filled($deviceId)) {
            $query2->where('id', (int) $deviceId);
        }

        $query2->chunkById(100, function ($devices) use (&$unkeyedDevices): void {
            foreach ($devices as $device) {
                $unkeyedDevices[$device->id] = $device;
            }
        });

        // 3. Identify legacy-hashed devices (hash present, no salt = unsalted legacy)
        /** @var array<int, BiometricDevice> $legacyDevices */
        $legacyDevices = [];
        $query3 = BiometricDevice::query()
            ->where('adms_enabled', true)
            ->whereNotNull('adms_comm_key_hash')
            ->whereNull('adms_comm_key_salt');

        if (filled($gymId)) {
            $query3->where('gym_id', (int) $gymId);
        }

        if (filled($deviceId)) {
            $query3->where('id', (int) $deviceId);
        }

        $query3->chunkById(100, function ($devices) use (&$legacyDevices): void {
            foreach ($devices as $device) {
                $legacyDevices[$device->id] = $device;
            }
        });

        // 4. Provisioning for keyless and legacy-hashed ADMS devices
        if ($provision) {
            foreach ($unkeyedDevices as $device) {
                // Generate a 6-digit numeric PIN (universally compatible with physical numeric keypad terminals)
                $plainKey = (string) random_int(100000, 999999);
                $salt = BiometricDevice::generateCommKeySalt();
                $hash = BiometricDevice::hashCommKey($plainKey, $salt);

                if (! $dryRun) {
                    DB::transaction(function () use ($device, $hash, $salt): void {
                        $device->adms_comm_key_hash = $hash;
                        $device->adms_comm_key_salt = $salt;
                        $device->save();
                    });

                    activity()
                        ->performedOn($device)
                        ->withProperties([
                            'gym_id' => $device->gym_id,
                            'action' => 'adms_comm_key_provisioned',
                        ])
                        ->log('ADMS device comm key provisioned with PBKDF2 hash');
                }

                $provisionedTable[] = [
                    'id' => $device->id,
                    'serial' => $device->device_serial,
                    'gym_id' => $device->gym_id,
                    'key' => $plainKey,
                ];

                $provisioned++;
            }

            foreach ($legacyDevices as $device) {
                $plainKey = (string) random_int(100000, 999999);
                $salt = BiometricDevice::generateCommKeySalt();
                $hash = BiometricDevice::hashCommKey($plainKey, $salt);

                if (! $dryRun) {
                    DB::transaction(function () use ($device, $hash, $salt): void {
                        $device->adms_comm_key_hash = $hash;
                        $device->adms_comm_key_salt = $salt;
                        $device->save();
                    });

                    activity()
                        ->performedOn($device)
                        ->withProperties([
                            'gym_id' => $device->gym_id,
                            'action' => 'adms_comm_key_reprovisioned',
                        ])
                        ->log('ADMS device legacy comm key re-provisioned with PBKDF2 hash');
                }

                $provisionedTable[] = [
                    'id' => $device->id,
                    'serial' => $device->device_serial,
                    'gym_id' => $device->gym_id,
                    'key' => $plainKey,
                ];

                $provisioned++;
            }

            if (filled($provisionedTable)) {
                $this->newLine();
                $this->info('--- Provisioned ADMS Device Keys (Configure on Physical Terminals) ---');
                $this->table(
                    ['Device ID', 'Device Serial', 'Gym ID', 'Provisioned Comm Key'],
                    $provisionedTable
                );
            }
        } else {
            if (filled($unkeyedDevices)) {
                $this->newLine();
                $this->warn(sprintf(
                    'Notice: %d adms_enabled device(s) have no comm key configured. Run with `--provision` to generate 6-digit keys before enforcing ADMS_REQUIRE_COMM_KEY.',
                    count($unkeyedDevices)
                ));
                $this->table(
                    ['Device ID', 'Device Serial', 'Gym ID'],
                    array_map(fn ($d) => ['id' => $d->id, 'serial' => $d->device_serial, 'gym_id' => $d->gym_id], array_values($unkeyedDevices))
                );
            }

            if (filled($legacyDevices)) {
                $this->newLine();
                $this->warn(sprintf(
                    'Notice: %d adms_enabled device(s) have legacy unsalted comm key hashes (no salt). Run with `--provision` to generate new PBKDF2 salted keys.',
                    count($legacyDevices)
                ));
                $this->table(
                    ['Device ID', 'Device Serial', 'Gym ID'],
                    array_map(fn ($d) => ['id' => $d->id, 'serial' => $d->device_serial, 'gym_id' => $d->gym_id], array_values($legacyDevices))
                );
            }
        }

        $summary = sprintf(
            'ADMS comm key backfill completed. %d migrated, %d refused, %d provisioned%s.',
            $migrated,
            $refused,
            $provisioned,
            $dryRun ? ' (dry run)' : ''
        );
        $this->info($summary);

        return self::SUCCESS;
    }
}
