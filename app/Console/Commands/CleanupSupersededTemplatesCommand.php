<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Biometrics\Services\BiometricDeProvisioner;
use App\Enums\BiometricDeProvisionOutcome;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricTemplate;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class CleanupSupersededTemplatesCommand extends Command
{
    protected $signature = 'biometrics:cleanup-superseded-templates
        {--days=30 : Retention period in days for superseded templates}
        {--pending-days=7 : Retention period in days for abandoned pending placeholders}
        {--dry-run : Preview without deleting records}';

    protected $description = 'Retire superseded biometric templates and abandoned pending placeholders older than the retention threshold';

    public function handle(BiometricDeProvisioner $deProvisioner): int
    {
        $days = (int) $this->option('days');
        if ($days <= 0) {
            $this->error('--days must be an integer greater than 0.');

            return self::FAILURE;
        }

        $pendingDays = (int) $this->option('pending-days');
        if ($pendingDays <= 0) {
            $this->error('--pending-days must be an integer greater than 0.');

            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $cutoff = now()->subDays($days);
        $pendingCutoff = now()->subDays($pendingDays);

        $query = BiometricTemplate::query()
            ->where(function ($q) use ($cutoff, $pendingCutoff): void {
                $q->where(function (Builder $sub) use ($cutoff): void {
                    $sub->where('sync_status', BiometricTemplateSyncStatusEnum::Superseded)
                        ->where('updated_at', '<', $cutoff);
                })->orWhere(function (Builder $sub) use ($pendingCutoff): void {
                    $sub->where('sync_status', BiometricTemplateSyncStatusEnum::Pending)
                        ->where('created_at', '<', $pendingCutoff);
                });
            });

        if ($dryRun) {
            $supersededCount = (clone $query)->where('sync_status', BiometricTemplateSyncStatusEnum::Superseded)->count();
            $pendingCount = (clone $query)->where('sync_status', BiometricTemplateSyncStatusEnum::Pending)->count();
            $count = $supersededCount + $pendingCount;
            $this->info("Found {$count} template(s) to purge (dry-run). ({$supersededCount} superseded, {$pendingCount} pending)");

            return self::SUCCESS;
        }

        $retiredSuperseded = 0;
        $retiredUndeliveredPending = 0;
        $deProvisionedPending = 0;
        $deferredPending = 0;

        $query->chunkById(100, function ($templates) use ($deProvisioner, &$retiredSuperseded, &$retiredUndeliveredPending, &$deProvisionedPending, &$deferredPending): void {
            foreach ($templates as $template) {
                if ($template->sync_status === BiometricTemplateSyncStatusEnum::Pending) {
                    if (! $template->enrolled_by_device_id) {
                        $retiredUndeliveredPending++;
                        $template->delete();

                        continue;
                    }

                    AdmsDeviceCommand::cancelInFlightFor(
                        $template->enrolled_by_device_id,
                        $template->resolvedCommandPin()
                    );

                    if (! $template->wasDeliveredToHardware()) {
                        $retiredUndeliveredPending++;
                        $template->delete();

                        continue;
                    }

                    if ($deProvisioner->deprovision($template) === BiometricDeProvisionOutcome::DeferredToSibling) {
                        $deferredPending++;

                        continue;
                    }

                    $deProvisionedPending++;

                    continue;
                }

                $retiredSuperseded++;
                $template->delete();
            }
        });

        // The buckets are disjoint, so the headline count is the number of rows actually
        // retired and cannot exceed the number of candidates visited.
        $retiredCount = $retiredSuperseded + $retiredUndeliveredPending;
        $visitedPending = $retiredUndeliveredPending + $deProvisionedPending + $deferredPending;

        $this->info("Retired {$retiredCount} template(s). ({$retiredSuperseded} superseded, {$retiredUndeliveredPending} undelivered pending)");

        if ($visitedPending > 0) {
            $this->info("{$visitedPending} pending placeholder(s) were hardware-delivered and remain pending erasure.");
        }

        if ($deProvisionedPending > 0) {
            $this->info("{$deProvisionedPending} pending placeholder(s) handed to hardware de-provisioning.");
        }

        if ($deferredPending > 0) {
            $this->warn("{$deferredPending} pending placeholder(s) deferred: a sibling template still needs the device credential.");
        }

        return self::SUCCESS;
    }
}
