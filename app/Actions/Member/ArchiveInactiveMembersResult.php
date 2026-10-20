<?php

declare(strict_types=1);

namespace App\Actions\Member;

/**
 * Outcome of one ArchiveInactiveMembersAction run.
 *
 * A typed contract rather than an array because the four numbers answer four
 * different questions and are not interchangeable: totalEligible is what the
 * run set out to archive, archivedCount is what it actually changed,
 * skippedCount is what it declined to change, and failedCount is what it could
 * not change because the write threw.
 *
 * failedCount is part of the contract, not log-only, because "Successfully
 * archived 0 inactive member(s)" on a run where every member failed is a false
 * statement to an operator. The command warns when it is non-zero and never
 * prints "Successfully" over a run that failed for anyone.
 *
 * There is deliberately no hasDiscrepancy(). An earlier revision derived one
 * from `archivedCount !== totalEligible` and the command gated its warning on it,
 * which read as a second, competing signal; the command now gates on
 * skippedCount directly, which is the number it can actually explain.
 *
 * ── On the arithmetic ─────────────────────────────────────────────────────────
 * There is NO invariant that `archivedCount + skippedCount === totalEligible`,
 * and asserting one would be wrong. totalEligible is a COUNT(*) taken before
 * the first chunk is read; members can be archived by an operator, another
 * scheduled run, or the panel between that count and the chunk that would have
 * picked them up. Such a member stays in totalEligible, is re-selected by
 * `archivableMembers()` only if it was not archived before its chunk was read,
 * and is then either written (archivedCount) or declined (skippedCount). A
 * member archived after its chunk was read is simply absent from the chunk, so
 * it contributes to totalEligible and to neither counter. The sum can therefore
 * come in under the total, and that is normal operation rather than a fault.
 *
 * skippedCount is genuinely reachable, and only because of how archive() is
 * built: MemberArchiveService::archive() re-reads is_archived from the
 * persisted row under a row lock inside its own transaction, so a member
 * another writer archived between a chunk being selected and the write being
 * attempted is observed as already archived and counted as skipped rather than
 * silently re-archived.
 */
readonly class ArchiveInactiveMembersResult
{
    public function __construct(
        public int $archivedCount,
        public int $skippedCount,
        public int $totalEligible,
        public int $failedCount = 0,
    ) {}
}
