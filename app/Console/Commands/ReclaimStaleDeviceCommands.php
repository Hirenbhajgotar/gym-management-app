<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AdmsCommandStatusEnum;
use App\Models\AdmsDeviceCommand;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class ReclaimStaleDeviceCommands extends Command
{
    protected $signature = 'app:reclaim-stale-device-commands {--lease-seconds= : The stale lease duration in seconds (defaults to biometrics.adms.command_lease_seconds)}';

    protected $description = 'Recover or fail ADMS device commands whose lease expired without acknowledgement';

    public function handle(): int
    {
        $leaseOption = $this->option('lease-seconds');
        $leaseSeconds = $leaseOption !== null && (int) $leaseOption > 0
            ? (int) $leaseOption
            : config()->integer('biometrics.adms.command_lease_seconds', 600);

        $staleThreshold = now()->subSeconds($leaseSeconds);

        $reclaimedCount = 0;
        $failedCount = 0;

        AdmsDeviceCommand::query()
            ->where('status', AdmsCommandStatusEnum::SENT)
            ->where(function (Builder $query) use ($staleThreshold) {
                $query->whereNull('leased_at')
                    ->orWhere('leased_at', '<', $staleThreshold)
                    ->orWhere(function (Builder $sub) {
                        $sub->whereNotNull('expires_at')
                            ->where('expires_at', '<', now());
                    });
            })
            ->chunkById(100, function ($staleCommands) use (&$reclaimedCount, &$failedCount) {
                foreach ($staleCommands as $command) {
                    if ($command->attempt_count >= 3) {
                        $affected = AdmsDeviceCommand::query()
                            ->where('id', $command->id)
                            ->where('status', AdmsCommandStatusEnum::SENT)
                            ->update([
                                'status' => AdmsCommandStatusEnum::FAILED,
                                'device_return_code' => 'timeout_max_attempts',
                                'leased_at' => null,
                                'expires_at' => null,
                            ]);

                        if ($affected > 0) {
                            $failedCount++;

                            Log::warning('ADMS: Device command failed after maximum retry attempts (3)', [
                                'command_id' => $command->id,
                                'device_id' => $command->device_id,
                                'gym_id' => $command->gym_id,
                                'command_type' => $command->command_type,
                                'attempt_count' => $command->attempt_count,
                            ]);
                        }
                    } else {
                        $affected = AdmsDeviceCommand::query()
                            ->where('id', $command->id)
                            ->where('status', AdmsCommandStatusEnum::SENT)
                            ->update([
                                'status' => AdmsCommandStatusEnum::PENDING,
                                'leased_at' => null,
                                'expires_at' => null,
                            ]);

                        if ($affected > 0) {
                            $reclaimedCount++;

                            Log::info('ADMS: Device command lease expired, returned to pending for retry', [
                                'command_id' => $command->id,
                                'device_id' => $command->device_id,
                                'command_type' => $command->command_type,
                                'attempt_count' => $command->attempt_count,
                            ]);
                        }
                    }
                }
            });

        $this->info("Reclaimed {$reclaimedCount} commands to pending; marked {$failedCount} as failed.");

        return self::SUCCESS;
    }
}
