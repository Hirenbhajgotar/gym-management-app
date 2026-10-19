<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BiometricTemplateSyncStatusEnum: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Propagated = 'propagated';
    case Synced = 'synced';
    case PendingDeletion = 'pending_deletion';
    case Deleted = 'deleted';
    case Superseded = 'superseded';

    /**
     * The erasure obligation cannot be discharged by the system.
     *
     * Two producers reach this state, and the operator's next step differs. A cloud-backed row
     * with no provider reference has nothing to erase with; a cloud row whose provider kept
     * refusing past `biometrics.cloud_erasure_cooldown_hours` still holds its reference and is
     * fixable by repairing the provider credential. Either way the row is parked here instead of
     * `PendingDeletion` so the reconciler stops selecting it every hour.
     *
     * It is surfaced to operators through the `BiometricTemplatesRelationManager` "Needs
     * Operator" filter and the `DeletionUnresolvable` badge. The exit is that manager's "Retry
     * Erasure" action, which clears the recorded cooldown and re-drives the obligation. The row
     * is retained because the obligation does not disappear when the system cannot act on it.
     */
    case DeletionUnresolvable = 'deletion_unresolvable';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Propagated => 'Propagated',
            self::Synced => 'Synced',
            self::PendingDeletion => 'Pending Hardware Deletion',
            self::Deleted => 'Deleted',
            self::Superseded => 'Superseded',
            self::DeletionUnresolvable => 'Unresolvable — Operator Required',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Propagated => 'warning',
            self::Synced => 'success',
            self::PendingDeletion => 'danger',
            self::Deleted => 'danger',
            self::Superseded => 'gray',
            self::DeletionUnresolvable => 'danger',
        };
    }

    /**
     * Whether the row still represents a live credential or an outstanding erasure.
     *
     * `Deleted` and `Superseded` are terminal for the credential; a
     * `DeletionUnresolvable` row is retained for audit only and must not be treated
     * as live by any access decision.
     *
     * This predicate is the single definition of the state set, and
     * {@see self::liveOrPendingCredentialStates()} is derived from it rather than repeated as
     * a literal, so a caller that can read the predicate and a caller that has to build a
     * query cannot disagree about what "live or pending" means.
     */
    public function representsLiveOrPendingCredential(): bool
    {
        return match ($this) {
            self::Pending,
            self::Propagated,
            self::Synced,
            self::PendingDeletion => true,
            self::Deleted,
            self::Superseded,
            self::DeletionUnresolvable => false,
        };
    }

    /**
     * Whether the row represents a credential with no erasure requested for it yet.
     *
     * `PendingDeletion` is deliberately excluded: finishing an outstanding erasure is the
     * hourly reconciler's job, and a sweep or a sibling fan-out that re-drove it would
     * duplicate that ownership.
     */
    public function representsLiveCredential(): bool
    {
        return match ($this) {
            self::Pending,
            self::Propagated,
            self::Synced => true,
            self::PendingDeletion,
            self::Deleted,
            self::Superseded,
            self::DeletionUnresolvable => false,
        };
    }

    /**
     * Whether a terminal holds this row's credential, or has been asked to hold it.
     *
     * `Synced` is a credential the terminal confirmed, and `Propagated` is a fan-out row the
     * terminal has been sent or has accepted. This predicate is the single definition of the
     * state set, and {@see self::terminalCredentialStates()} is derived from it rather than
     * repeated as a literal, so a caller that can read the predicate and a caller that has to
     * build a query cannot disagree about what a terminal-held credential is.
     */
    public function representsTerminalHeldCredential(): bool
    {
        return match ($this) {
            self::Synced,
            self::Propagated => true,
            self::Pending,
            self::PendingDeletion,
            self::Deleted,
            self::Superseded,
            self::DeletionUnresolvable => false,
        };
    }

    /**
     * Whether a hardware acknowledgement may still promote this row.
     *
     * A `Pending` placeholder becomes `Synced`, and a `Propagated` fan-out row is stamped with
     * its hardware confirmation. Everything else is already terminal, already awaiting
     * erasure, or already synced, so it is resolved elsewhere. This predicate is the single
     * definition of the state set, and {@see self::awaitingHardwareConfirmationStates()} is
     * derived from it rather than repeated as a literal.
     */
    public function representsAwaitingHardwareConfirmation(): bool
    {
        return match ($this) {
            self::Pending,
            self::Propagated => true,
            self::Synced,
            self::PendingDeletion,
            self::Deleted,
            self::Superseded,
            self::DeletionUnresolvable => false,
        };
    }

    /**
     * Whether this state is a candidate for operator attention from the template row alone.
     *
     * This is the SQL-expressible half of {@see \App\Models\BiometricTemplate::requiresOperatorAttention()}:
     * a `DeletionUnresolvable` row always needs an operator, and a `PendingDeletion` row is a
     * candidate but may not yet qualify — its `user_delete` delivery cap lives on the command
     * log, not on the template, so only that predicate has the final say.
     *
     * This predicate is the single definition of the state set, and
     * {@see self::operatorAttentionCandidateStates()} is derived from it rather than repeated
     * as a literal, so a caller that can read the predicate and a caller that has to build a
     * query cannot disagree about what a candidate is.
     */
    public function representsOperatorAttentionCandidate(): bool
    {
        return match ($this) {
            self::PendingDeletion,
            self::DeletionUnresolvable => true,
            self::Pending,
            self::Propagated,
            self::Synced,
            self::Deleted,
            self::Superseded => false,
        };
    }

    /**
     * Every state that still represents a live credential or an outstanding erasure.
     *
     * Derived from {@see self::representsLiveOrPendingCredential()} so the instance
     * predicate and the query-level set are one rule.
     *
     * @return array<int, self>
     */
    public static function liveOrPendingCredentialStates(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $status): bool => $status->representsLiveOrPendingCredential()
        ));
    }

    /**
     * States that represent a credential a terminal or provider may be holding but which no
     * de-provisioning has yet been requested for.
     *
     * Derived from {@see self::representsLiveCredential()}, so the two methods on this enum
     * cannot disagree about what "live" means.
     *
     * @return array<int, self>
     */
    public static function liveCredentialStates(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $status): bool => $status->representsLiveCredential()
        ));
    }

    /**
     * States in which a terminal holds the credential or has been asked to hold it.
     *
     * Used wherever the question is "does a terminal share this PIN's credential":
     * `BiometricTemplate::hasActiveSiblingOnSameDevice()` (the ADMS `user_delete` erases a
     * whole PIN) and `biometrics:reconcile-enrollments` (an active row whose `user_add`
     * should exist on the device).
     *
     * Derived from {@see self::representsTerminalHeldCredential()} so the instance predicate
     * and the query-level set are one rule.
     *
     * @return array<int, self>
     */
    public static function terminalCredentialStates(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $status): bool => $status->representsTerminalHeldCredential()
        ));
    }

    /**
     * States a hardware `user_add` acknowledgement may promote.
     *
     * A `Pending` placeholder becomes `Synced`; a `Propagated` fan-out row is stamped with its
     * hardware confirmation. Anything already terminal, already awaiting erasure, or already
     * synced is resolved elsewhere.
     *
     * Derived from {@see self::representsAwaitingHardwareConfirmation()} so the instance
     * predicate and the query-level set are one rule.
     *
     * @return array<int, self>
     */
    public static function awaitingHardwareConfirmationStates(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $status): bool => $status->representsAwaitingHardwareConfirmation()
        ));
    }

    /**
     * States that are candidates for the operator attention surface.
     *
     * Used wherever the question is "could this row be an erasure the system cannot discharge
     * by itself": the `BiometricTemplatesRelationManager` "Needs Operator" filter and the
     * `BiometricOperatorAttentionWidget` narrow their SQL to this set before applying
     * {@see \App\Models\BiometricTemplate::requiresOperatorAttention()}, which is the final
     * word because the `PendingDeletion` delivery cap is only visible on the command log.
     *
     * Derived from {@see self::representsOperatorAttentionCandidate()} so the instance
     * predicate and the query-level set are one rule.
     *
     * @return array<int, self>
     */
    public static function operatorAttentionCandidateStates(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $status): bool => $status->representsOperatorAttentionCandidate()
        ));
    }
}
