<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\HtmlString;

/**
 * Closes a relation manager's mutating paths while its owner member is archived.
 *
 * Archived members stay fully readable — payments are what revenue reporting
 * bills against, measurements are the history behind a BMI trend, and biometric
 * templates are the audit trail of what was enrolled and when it was erased.
 * What must not stay possible is rewriting any of that after the record was
 * closed, because a re-measurement or a payment edit applied underneath an
 * archived member quietly makes a revoked record live again without anyone
 * restoring it.
 *
 * Two layers, both needed:
 *
 * - {@see self::isReadOnly()} feeds Filament's own gating, so the standard
 *   Create/Edit/Delete/DeleteBulk actions are not offered at all.
 * - {@see self::guardArchivedOwner()} refuses server-side at the point of the
 *   write. A hidden button is not a guarantee: a Livewire call can invoke an
 *   action that was never rendered, so every mutating path inside the manager
 *   calls the guard itself rather than relying on the framework having hidden
 *   the affordance.
 *
 * Implemented once here rather than pasted into each manager. A security guard
 * with four near-identical copies is four places to update and four places to
 * forget, and "forget" is silent — the copies drift and one of them stops
 * guarding.
 *
 * A using class supplies two strings: {@see self::archivedOwnerSubject()} for
 * the refusal message and {@see self::archivedOwnerNoticeBody()} for the
 * banner, so the operator is told what is frozen rather than being given a
 * generic notice.
 *
 * @property-read \Illuminate\Database\Eloquent\Model|null $ownerRecord
 */
trait GuardsArchivedOwner
{
    public function isReadOnly(): bool
    {
        return parent::isReadOnly() || $this->isOwnerArchived();
    }

    /**
     * Whether the member owning this relation has been archived.
     */
    protected function isOwnerArchived(): bool
    {
        return (bool) $this->ownerRecord?->is_archived;
    }

    /**
     * Refuse a write while the owner member is archived.
     *
     * @throws AuthorizationException
     */
    protected function guardArchivedOwner(): void
    {
        throw_unless(
            ! $this->isOwnerArchived(),
            AuthorizationException::class,
            $this->archivedOwnerSubject() . ' cannot be changed while the member is archived. Restore the member first.'
        );
    }

    /**
     * Read-only banner rendered above the table while the owner member is archived.
     */
    protected function archivedOwnerNotice(): HtmlString
    {
        return new HtmlString(
            '<div class="rounded-lg bg-warning-50 p-4 text-sm text-warning-800 ring-1 ring-inset ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-200 dark:ring-warning-400/20">'
            . '<p class="font-semibold">This member is archived.</p>'
            . '<p class="mt-1">' . e($this->archivedOwnerNoticeBody()) . '</p>'
            . '</div>'
        );
    }

    /**
     * What is frozen, as the operator should see it named. E.g. 'Payments'.
     */
    abstract protected function archivedOwnerSubject(): string;

    /**
     * One sentence explaining why the rows are still visible but frozen.
     */
    abstract protected function archivedOwnerNoticeBody(): string;

    /**
     * Read-only banner, or null when the owner is live and there is nothing to say.
     */
    protected function archivedOwnerHeader(): ?HtmlString
    {
        return $this->isOwnerArchived() ? $this->archivedOwnerNotice() : null;
    }
}
