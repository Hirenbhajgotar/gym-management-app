<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Biometrics\ValueObjects\AdmsCommandKey;
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
        $orphanedCount = 0;
        $maxAttempts = AdmsCommandKey::maxAttempts();

        AdmsDeviceCommand::query()
            ->where('status', AdmsCommandStatusEnum::SENT)
            // A preserved delivered `user_add` is durable evidence, not in-flight work.
            // It is deliberately kept at `SENT` by the terminal tombstone and must outlive
            // every lease window; cancelling it would make `wasDeliveredToHardware()` false
            // and converge a live credential to `Deleted` with no erasure.
            ->whereNull('preserved_evidence_at')
            ->where(function (Builder $query) use ($staleThreshold) {
                $query->whereNull('leased_at')
                    ->orWhere('leased_at', '<', $staleThreshold)
                    ->orWhere(function (Builder $sub) {
                        $sub->whereNotNull('expires_at')
                            ->where('expires_at', '<', now());
                    });
            })
            ->chunkById(100, function ($staleCommands) use (&$reclaimedCount, &$failedCount, &$orphanedCount, $maxAttempts) {
                foreach ($staleCommands as $command) {
                    if ($command->device_id === null) {
                        // Belt and braces alongside `preserved_evidence_at`: a delivered
                        // `user_add` for a deleted terminal is evidence even if the marker
                        // is missing (a restored dump, or a row preserved before this
                        // column existed). Never cancel it.
                        if ($command->command_type === 'user_add') {
                            $orphanedCount++;

                            continue;
                        }

                        // The terminal was deleted, so no poll will ever lease this
                        // row again. Returning it to PENDING would resurrect a
                        // command that cannot be delivered and would leave it
                        // immortal: prunable() only keeps PENDING rows alive.
                        $affected = AdmsDeviceCommand::query()
                            ->where('id', $command->id)
                            ->where('status', AdmsCommandStatusEnum::SENT)
                            ->update([
                                'status' => AdmsCommandStatusEnum::CANCELLED,
                                'device_return_code' => AdmsDeviceCommand::TERMINAL_DELETED_CODE,
                                'leased_at' => null,
                                'expires_at' => null,
                            ]);

                        if ($affected > 0) {
                            $orphanedCount++;

                            Log::warning('ADMS: stale command for a deleted terminal cancelled; it can never be delivered', [
                                'command_id' => $command->id,
                                'gym_id' => $command->gym_id,
                                'command_type' => $command->command_type,
                                'attempt_count' => $command->attempt_count,
                            ]);
                        }

                        continue;
                    }

                    if ($command->attempt_count >= $maxAttempts) {
                        $affected = AdmsDeviceCommand::query()
                            ->where('id', $command->id)
                            ->where('status', AdmsCommandStatusEnum::SENT)
                            ->update([
                                'status' => AdmsCommandStatusEnum::FAILED,
                                'device_return_code' => AdmsDeviceCommand::TIMEOUT_MAX_ATTEMPTS_CODE,
                                'leased_at' => null,
                                'expires_at' => null,
                            ]);

                        if ($affected > 0) {
                            $failedCount++;

                            Log::warning("ADMS: Device command failed after maximum retry attempts ({$maxAttempts})", [
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

        $this->info("Reclaimed {$reclaimedCount} commands to pending; marked {$failedCount} as failed; cancelled {$orphanedCount} orphaned commands.");

        return self::SUCCESS;
    }
}
