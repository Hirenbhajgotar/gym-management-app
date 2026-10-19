<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GymMember;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Gate for any code path that writes a member's archive state outside the panel.
 *
 * Archiving and unarchiving are separately permissioned abilities, not a
 * side-effect of being allowed to edit a member. Both importers, however, are
 * authorised on `import_gym_member` alone — which `defaultRolePermissions()`
 * grants to receptionist and trainer — so without this gate a desk user who is
 * explicitly denied archive/unarchive could still flip the flag by uploading a
 * spreadsheet. The directions are not equivalent in risk, which is why they are
 * separate calls rather than one: unarchiving restores
 * `allow_whatsapp_marketing` from the archive snapshot, returns the member to
 * the roster, and hands door access straight back. It is the more dangerous of
 * the two, and it is the direction that was ungated.
 *
 * Fails closed on "who is doing this": if no operator can be resolved the write
 * is refused rather than allowed. An importer that cannot attribute the change
 * to a person must not be able to make it.
 *
 * The caller owns the Spatie team context. `$actor->can()` resolves the
 * operator's gym-scoped roles through `getPermissionsTeamId()`, which a queue
 * worker does not have — without a team id every gym-scoped role is invisible
 * and this gate refuses every transition, inert-strict rather than merely
 * strict (the same rule {@see \App\Jobs\FanOutBiometricConsentRequestsJob}
 * documents). Callers that run outside the web middleware group — both
 * importers — set the team id around the gated section and restore it in a
 * `finally`; callers already inside a tenant panel inherit it from the
 * TeamsPermission middleware.
 */
class MemberArchiveAuthorization
{
    /**
     * Refuse unless the acting operator may archive gym members.
     *
     * $member is the record being archived when one exists, so the policy's
     * per-record tenant check runs on this path too; a row that does not exist
     * yet is checked against the model class, which is permission-only.
     *
     * @throws AuthorizationException
     */
    public function assertCanArchive(?User $operator = null, ?GymMember $member = null): void
    {
        $this->assertCan(
            $operator,
            $member,
            'archive',
            'This change would write is_archived but no authenticated operator is attached, so the archive permission cannot be verified.',
            'You are not authorised to archive gym members.'
        );
    }

    /**
     * Refuse unless the acting operator may restore archived gym members.
     *
     * @throws AuthorizationException
     */
    public function assertCanUnarchive(?User $operator = null, ?GymMember $member = null): void
    {
        $this->assertCan(
            $operator,
            $member,
            'unarchive',
            'This change would clear is_archived but no authenticated operator is attached, so the unarchive permission cannot be verified.',
            'You are not authorised to restore archived gym members.'
        );
    }

    /**
     * @throws AuthorizationException
     */
    private function assertCan(?User $operator, ?GymMember $member, string $ability, string $unattributableMessage, string $unauthorisedMessage): void
    {
        // No ambient Auth::user() fallback. On a shared worker the guard holds
        // whatever identity the previous job left logged in, so reading it here
        // would authorise an unattributable write as the previous operator —
        // the exact fail-open this gate exists to prevent. Every caller passes
        // the operator it resolved explicitly.
        if (! $operator instanceof User) {
            throw new AuthorizationException($unattributableMessage);
        }

        throw_unless(
            $operator->can($ability, $member ?? GymMember::class),
            AuthorizationException::class,
            $unauthorisedMessage
        );
    }
}
