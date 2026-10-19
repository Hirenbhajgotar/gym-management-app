<?php

declare(strict_types=1);

namespace App\Actions\Member;

use App\Services\MemberArchiveService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class ArchiveInactiveMembersAction
{
    private const CHUNK_SIZE = 100;

    public function __construct(
        private MemberArchiveService $archiveService,
    ) {}

    /**
     * Archive all members whose membership ended more than the given number of years ago.
     *
     * There is exactly one success exit point, and it always logs. A scheduled
     * run that found nothing eligible is still evidence that the schedule fired
     * and the archive query ran; before, the zero case returned early and
     * produced no line at all, so "nothing to archive" was indistinguishable
     * from "this job never executed".
     *
     * Members are archived one at a time, each in its own transaction, and a
     * failure on a single member is logged and counted rather than aborting the
     * run (see {@see archiveEligibleMembers()}). Only a failure of the query
     * itself — which would make the result meaningless — reaches the catch here
     * and fails the run, re-thrown so the scheduler marks it failed. The log line
     * records how far it got, which the exception alone cannot say.
     */
    public function execute(int $years = 2): ArchiveInactiveMembersResult
    {
        $archivedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        try {
            $totalEligible = $this->archiveEligibleMembers($years, $archivedCount, $skippedCount, $failedCount);
        } catch (Throwable $exception) {
            Log::error('ArchiveInactiveMembersAction failed mid-run', [
                'years_cutoff' => $years,
                'archived_before_failure' => $archivedCount,
                'skipped_before_failure' => $skippedCount,
                'failed_before_failure' => $failedCount,
                'committed_members_are_durable' => true,
                'exception' => $exception,
            ]);

            throw $exception;
        }

        $result = new ArchiveInactiveMembersResult(
            archivedCount: $archivedCount,
            skippedCount: $skippedCount,
            totalEligible: $totalEligible,
            failedCount: $failedCount,
        );

        // Named construction keeps this call site honest: adding a field to the
        // DTO is a compile-time break here rather than a silently misaligned
        // positional argument. The context keys are the operator-facing
        // contract and are left exactly as they were; a zero-eligible run is
        // already distinguishable by total_eligible => 0, and by the line being
        // emitted at all where previously nothing was written.
        Log::info('ArchiveInactiveMembersAction executed', [
            'years_cutoff' => $years,
            'total_eligible' => $result->totalEligible,
            'archived_count' => $result->archivedCount,
            'skipped_count' => $result->skippedCount,
            'failed_count' => $result->failedCount,
        ]);

        return $result;
    }

    /**
     * Walk the eligible set in chunks, archiving each member in its own transaction.
     *
     * Per-member, not per-chunk. Every archive() call opens the member's own
     * transaction (the row lock and the four archive columns) and returns once it
     * has committed, so the biometric revocation that follows it is no longer
     * swallowed by a chunk-wide transaction it cannot be part of. And because a
     * single member's failure (a lock wait against a slow enrolment, a bad row)
     * is contained, the members around it stay committed instead of a whole
     * hundred-row chunk aborting.
     *
     * A failure is logged and counted, and the sweep continues: archiving is
     * idempotent, so a member that failed is simply re-selected on the next run.
     *
     * @param  int  $archivedCount  Folded by reference; only incremented after a
     *                              member's own transaction returned.
     * @param  int  $skippedCount  Folded by reference; a member another writer
     *                             already archived first (archive() returned false).
     * @param  int  $failedCount  Folded by reference; a member whose archive()
     *                            threw. Logged, and retried on the next run.
     */
    private function archiveEligibleMembers(int $years, int &$archivedCount, int &$skippedCount, int &$failedCount): int
    {
        $query = $this->archiveService->archivableMembers($years);
        $totalEligible = $query->count();

        if ($totalEligible === 0) {
            return 0;
        }

        $query->chunkById(self::CHUNK_SIZE, function (Collection $members) use (&$archivedCount, &$skippedCount, &$failedCount): void {
            foreach ($members as $member) {
                try {
                    if ($this->archiveService->archiveAsSystem($member)) {
                        $archivedCount++;

                        continue;
                    }

                    $skippedCount++;
                } catch (Throwable $e) {
                    $failedCount++;

                    Log::error('ArchiveInactiveMembersAction: failed to archive a member; continuing', [
                        'member_id' => $member->getKey(),
                        'gym_id' => $member->gym_id,
                        'error_type' => $e::class,
                    ]);
                }
            }
        });

        return $totalEligible;
    }
}
