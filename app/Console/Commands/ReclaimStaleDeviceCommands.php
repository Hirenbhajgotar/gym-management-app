<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdmsDeviceCommand;
use Illuminate\Console\Command;

class ReclaimStaleDeviceCommands extends Command
{
    protected $signature = 'app:reclaim-stale-device-commands {--lease-seconds= : The stale lease duration in seconds (defaults to biometrics.adms.command_lease_seconds)}';

    protected $description = 'Recover or fail ADMS device commands whose lease expired without acknowledgement';

    public function handle(): int
    {
        $leaseOption = $this->option('lease-seconds');
        $leaseSeconds = $leaseOption !== null && (int) $leaseOption > 0
            ? (int) $leaseOption
            : null;

        // The ledger owns the sweep and every branch inside it. This adapter only
        // translates the CLI option into intent and renders the typed summary.
        $summary = AdmsDeviceCommand::reclaimExpiredLeases($leaseSeconds);

        $this->info("Reclaimed {$summary->reclaimedCount()} commands to pending; marked {$summary->failedCount()} as failed; cancelled {$summary->orphanedCount()} orphaned commands.");

        return self::SUCCESS;
    }
}
