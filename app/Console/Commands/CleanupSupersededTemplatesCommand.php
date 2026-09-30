<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\BiometricTemplate;
use Illuminate\Console\Command;

class CleanupSupersededTemplatesCommand extends Command
{
    protected $signature = 'biometrics:cleanup-superseded-templates
        {--days=30 : Retention period in days}
        {--dry-run : Preview without deleting records}';

    protected $description = 'Purge superseded biometric templates older than the retention threshold';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days <= 0) {
            $this->error('--days must be an integer greater than 0.');

            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $query = BiometricTemplate::query()
            ->where('sync_status', BiometricTemplateSyncStatusEnum::Superseded)
            ->where('updated_at', '<', $cutoff);

        if ($dryRun) {
            $count = $query->count();
            $this->info("Found {$count} superseded template(s) older than {$days} days to purge (dry-run).");

            return self::SUCCESS;
        }

        $purgedCount = 0;

        $query->chunkById(100, function ($templates) use (&$purgedCount): void {
            foreach ($templates as $template) {
                $template->delete();
                $purgedCount++;
            }
        });

        $this->info("Purged {$purgedCount} superseded template(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
