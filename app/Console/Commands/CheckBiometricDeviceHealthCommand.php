<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BiometricDeviceStatusEnum;
use App\Models\BiometricDevice;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckBiometricDeviceHealthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'biometrics:check-health {--timeout= : Timeout threshold in minutes (overrides config)} {--misses= : Consecutive misses threshold (overrides config)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check biometric device heartbeat freshness and mark unresponsive devices as inactive after consecutive misses';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timeoutMinutes = (int) ($this->option('timeout') ?: config()->integer('biometrics.heartbeat_timeout_minutes', 15));
        $consecutiveMissThreshold = (int) ($this->option('misses') ?: config()->integer('biometrics.heartbeat_consecutive_misses', 1));
        $cutoff = now()->subMinutes($timeoutMinutes);

        $staleDevices = BiometricDevice::query()
            ->where('status', BiometricDeviceStatusEnum::Active)
            ->where(function (Builder $query) use ($cutoff) {
                $query->where(function (Builder $sub) use ($cutoff) {
                    $sub->whereNull('last_heartbeat_at')
                        ->where('created_at', '<', $cutoff);
                })->orWhere('last_heartbeat_at', '<', $cutoff);
            })
            ->get();

        $markedInactiveCount = 0;

        foreach ($staleDevices as $device) {
            $cacheKey = BiometricDevice::missCounterCacheKey($device->id);
            $misses = (int) Cache::increment($cacheKey);
            Cache::put($cacheKey, $misses, now()->addDay());

            if ($misses >= $consecutiveMissThreshold) {
                $device->update(['status' => BiometricDeviceStatusEnum::Inactive]);
                $markedInactiveCount++;

                Log::warning('Biometric device marked inactive due to consecutive missing heartbeats', [
                    'device_id' => $device->id,
                    'device_name' => $device->device_name,
                    'gym_id' => $device->gym_id,
                    'last_heartbeat_at' => $device->last_heartbeat_at?->toISOString(),
                    'consecutive_misses' => $misses,
                    'threshold_misses' => $consecutiveMissThreshold,
                    'timeout_minutes' => $timeoutMinutes,
                ]);
            } else {
                Log::info('Biometric device missed heartbeat check', [
                    'device_id' => $device->id,
                    'device_name' => $device->device_name,
                    'consecutive_misses' => $misses,
                    'threshold_misses' => $consecutiveMissThreshold,
                ]);
            }
        }

        $this->info("Biometric device health check completed. {$markedInactiveCount} device(s) marked inactive out of {$staleDevices->count()} stale device(s).");

        return self::SUCCESS;
    }
}
