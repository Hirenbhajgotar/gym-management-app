<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Member\ArchiveInactiveMembersAction;
use App\Services\MemberArchiveService;
use Illuminate\Console\Command;

class ArchiveInactiveGymMembersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:archive-inactive-gym-members
                            {--years=2 : Number of years of inactivity before archiving}
                            {--dry-run : Report what would be archived without writing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically archive gym members with no active membership plan or payment in over 2 years';

    /**
     * Execute the console command.
     */
    public function handle(ArchiveInactiveMembersAction $action, MemberArchiveService $archiveService): int
    {
        $rawYears = $this->option('years');

        if (! $this->optionIsValidYears($rawYears)) {
            $this->error('Invalid --years value. It must be a whole number of 1 or greater (received: ' . $rawYears . ').');

            return self::FAILURE;
        }

        $years = (int) $rawYears;
        $cutoff = $archiveService->cutoffDate($years);

        $this->info(
            'Searching for inactive members with membership_end prior to '
            . $cutoff->toDateString() . '...'
        );

        if ($this->option('dry-run')) {
            return $this->reportDryRun($archiveService, $years);
        }

        $result = $action->execute($years);

        if ($result->totalEligible === 0) {
            $this->info('No inactive members found matching archive criteria.');

            return self::SUCCESS;
        }

        if ($result->failedCount === 0) {
            $this->info("Successfully archived {$result->archivedCount} inactive member(s).");
        } else {
            // A failure is a member the run could not change. Reporting only
            // archivedCount let an all-fail run print "Successfully archived 0"
            // and exit SUCCESS, which reads as "nothing to do".
            $this->warn("Archived {$result->archivedCount} inactive member(s); {$result->failedCount} could not be archived and will be retried on the next run.");
        }

        // Gated on skippedCount, which is the one figure that can be explained to an
        // operator: these members were eligible when the run counted them and
        // something else got to them first. Comparing archivedCount against
        // totalEligible would warn about members that appeared mid-run too, and
        // would print "0 eligible member(s) were archived concurrently and
        // skipped." whenever nothing was skipped.
        if ($result->skippedCount > 0) {
            $this->warn("{$result->skippedCount} eligible member(s) were archived concurrently and skipped.");
        }

        return $result->failedCount > 0 && $result->archivedCount === 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * Report how many members a real run would archive, writing nothing.
     *
     * Goes through the same archivableMembers() query a real run would use and
     * the same cutoffDate() the header above already reported, so the number
     * shown is the number a subsequent real run would report — the point of a
     * dry run being that it is not a separate estimate.
     */
    private function reportDryRun(MemberArchiveService $archiveService, int $years): int
    {
        $wouldArchive = $archiveService->archivableMembers($years)->count();

        if ($wouldArchive === 0) {
            $this->info('No inactive members found matching archive criteria.');

            return self::SUCCESS;
        }

        $this->info("[DRY-RUN] {$wouldArchive} inactive member(s) would be archived. No changes were written.");

        return self::SUCCESS;
    }

    /**
     * Whether a --years option value is a whole number of at least 1.
     *
     * A bare (int) cast cannot be used for this: it turns 'abc' into 0, and a
     * 0-year cutoff archives every expired member in every gym.
     */
    private function optionIsValidYears(mixed $value): bool
    {
        if (! is_scalar($value)) {
            return false;
        }

        $raw = (string) $value;

        if (preg_match('/^\d+$/', $raw) !== 1) {
            return false;
        }

        return (int) $raw >= 1;
    }
}
