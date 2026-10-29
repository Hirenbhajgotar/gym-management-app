<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Date;
use Illuminate\Auth\Access\AuthorizationException;
use App\Biometrics\Services\BiometricDeProvisioner;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\BiometricTemplate;
use App\Models\GymMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single owner of a member's archive state.
 *
 * ── What archiving means ──────────────────────────────────────────────────────
 * Archiving is an ACCESS REVOCATION. It is enforced as one at every decision
 * point that can admit a member to the gym — `GymMember::canAccessGym()` refuses
 * `is_archived` before it reads membership, payment or freeze state, so QR
 * attendance, biometric check-in and web door access all deny an archived
 * member no matter which query hydrated them. `MembershipGuard::resolveReason()`
 * names it (`member_archived`) ahead of every other reason because the operator's
 * next action differs.
 *
 * Two consequences follow, and every site in the app must agree with both:
 *
 * 1. The credential that still opens the door has to be erased with the record.
 *    `archive()` therefore revokes every one of the member's live or pending
 *    biometric templates — not just the active ones, because a
 *    mid-enrolment placeholder is inactive yet already has its `user_add`
 *    queued — delegated to {@see BiometricDeProvisioner} so the PIN-scoped,
 *    sibling-aware rules cannot drift. That delegation also owns the
 *    cloud-vs-hardware dispatch: a provider-backed credential is erased through
 *    the provider, a hardware credential through the terminal. Two nets back it
 *    up: the daily `app:cleanup-expired-device-members` sweep now also selects
 *    archived members, and the ADMS reconciler refuses to promote a `Pending`
 *    template whose member is archived (parking it for erasure instead). An
 *    archived member holding a live ADMS template would be a credential the door
 *    still honours while the app says they are revoked.
 *
 * 2. `allow_whatsapp_marketing` is owned by this service for as long as the
 *    member is archived. Archiving forces it false and preserves the real prior
 *    decision in `whatsapp_marketing_snapshot`; unarchive restores from that
 *    snapshot. Any writer that touches the consent column directly, or that
 *    re-stamps `archived_at` outside a transition, corrupts the round trip.
 *
 * This class is the only place either transition is performed — the single
 * documented exception is creation (see below). Importers that need a
 * transition call `archive()`/`unarchive()` here rather than assigning the
 * columns; both take the acting operator explicitly and assert the permission
 * via {@see \App\Services\MemberArchiveAuthorization}, so the gate lives here
 * once instead of in every caller. The scheduled archive sweep, which has no
 * operator, uses the explicitly named {@see archiveAsSystem()};
 * {@see unarchiveAsSystem()} exists as the symmetric system path but has no
 * scheduled caller yet. A row that states the member's consent explicitly while
 * they stay archived goes through `recordConsentWhileArchived()` for the same
 * reason: the snapshot is this service's storage for as long as the member is
 * archived.
 *
 * That exception is creation: a member born already archived (a historical row
 * imported with a years-old `membership_end`) is inserted with the four archive
 * columns set directly by the importer, because there is no prior state to
 * transition from, no row to lock and no prior consent to snapshot. Every write
 * to those columns on an *existing* row still goes through this service.
 */
class MemberArchiveService
{
    /**
     * Archive a member on behalf of an operator.
     *
     * This is the gated entry point: the permission is asserted here, once, for
     * every operator-driven caller — the panel, both importers and the model
     * helper. There is no second policy to drift out of step, and no ambient
     * `Auth::user()` fallback: an operator that cannot be named explicitly is
     * refused rather than attributed to whoever the shared worker last logged
     * in (see {@see MemberArchiveAuthorization}). The scheduled sweep, which has
     * no operator, calls {@see archiveAsSystem()} instead.
     *
     * @throws AuthorizationException
     */
    public function archive(GymMember $member, ?User $operator): bool
    {
        resolve(MemberArchiveAuthorization::class)->assertCanArchive($operator, $member);

        return $this->archiveRow($member);
    }

    /**
     * Archive a member with no operator attached — the scheduled sweep.
     *
     * A separate, named method rather than a null-operator overload so the
     * absence of a permission check is a decision made at the one call site
     * entitled to make it, and is impossible to reach by accident from an
     * operator path.
     */
    public function archiveAsSystem(GymMember $member): bool
    {
        return $this->archiveRow($member);
    }

    /**
     * Archive a single member record and disable WhatsApp marketing.
     *
     * The "is this already archived?" decision is taken from a locked, freshly
     * re-read row, never from $member's in-memory attributes. A caller may be
     * holding a model loaded before a concurrent writer archived the same row —
     * the batch action's chunk callback is exactly that caller — and trusting
     * the stale copy would overwrite the other writer's archived_at with a
     * second timestamp and re-snapshot the already-forced false consent in
     * place of the real one, permanently losing it on unarchive.
     *
     * The write is issued against $locked rather than against the caller's
     * instance. $locked was read inside this transaction and is clean by
     * construction, so forceFill()+save() on it persists exactly the four
     * archive columns and provably cannot spill an unrelated in-memory edit the
     * caller happened to be holding. Model events and observers still fire —
     * it is an ordinary Eloquent save — and GymMemberObserver clears the
     * biometric summary cache off the back of it.
     *
     * The caller is then handed the persisted row rather than being asked to
     * refresh(): setRawAttributes(..., sync: true) copies the stored attributes
     * AND a matching original set, so a later save() is not a no-op re-write.
     * Any unrelated dirty attribute the caller was holding is dropped here,
     * which is the honest outcome — archive() never promised to persist
     * anything beyond these four columns.
     *
     * The returned false means "someone else owns this row now", which is what
     * makes the batch action's skippedCount a real figure rather than a
     * structural zero.
     */
    private function archiveRow(GymMember $member): bool
    {
        $archivedAt = now();

        $persisted = DB::transaction(function () use ($member, $archivedAt): ?GymMember {
            $locked = GymMember::query()
                ->whereKey($member->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof GymMember || $locked->is_archived) {
                return null;
            }

            $locked->forceFill([
                'whatsapp_marketing_snapshot' => $locked->allow_whatsapp_marketing,
                'is_archived' => true,
                'archived_at' => $archivedAt,
                'allow_whatsapp_marketing' => false,
            ])->save();

            return $locked;
        });

        if (! $persisted instanceof GymMember) {
            return false;
        }

        $member->setRawAttributes($persisted->getAttributes(), true);

        // The archive is durable; now make the revocation real. See the class
        // docblock: a live credential on an archived member is the one state
        // that must never exist.
        $this->revokeBiometricCredentials($persisted);

        return true;
    }

    /**
     * Restore a member from archive on behalf of an operator.
     *
     * @throws AuthorizationException
     */
    public function unarchive(GymMember $member, ?User $operator): bool
    {
        resolve(MemberArchiveAuthorization::class)->assertCanUnarchive($operator, $member);

        return $this->unarchiveRow($member);
    }

    /**
     * Restore a member from archive with no operator attached — the system path.
     */
    public function unarchiveAsSystem(GymMember $member): bool
    {
        return $this->unarchiveRow($member);
    }

    /**
     * Restore a member record from archive.
     *
     * Mirrors archive(): the persisted row is re-read under a row lock inside
     * its own transaction, so the consent snapshot being restored is the one
     * actually stored and the "still archived?" check cannot be satisfied by a
     * stale in-memory flag. The write goes through that locked row and the
     * caller is synchronised from it, for the same reason as archive().
     */
    private function unarchiveRow(GymMember $member): bool
    {
        $persisted = DB::transaction(function () use ($member): ?GymMember {
            $locked = GymMember::query()
                ->whereKey($member->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof GymMember || ! $locked->is_archived) {
                return null;
            }

            $snapshot = $locked->whatsapp_marketing_snapshot;

            $locked->forceFill([
                'is_archived' => false,
                'archived_at' => null,
                'allow_whatsapp_marketing' => $snapshot ?? $locked->allow_whatsapp_marketing,
                'whatsapp_marketing_snapshot' => null,
            ])->save();

            return $locked;
        });

        if (! $persisted instanceof GymMember) {
            return false;
        }

        $member->setRawAttributes($persisted->getAttributes(), true);

        return true;
    }

    /**
     * Record the member's own marketing-consent decision while they stay archived.
     *
     * A re-imported row can state the member's consent explicitly even while
     * the member remains archived. Writing `allow_whatsapp_marketing` directly
     * is forbidden while archived (the flag is forced false), so the snapshot —
     * the only consent storage this service owns for an archived member — is
     * where that decision belongs: the next unarchive() then restores the
     * decision the operator last stated rather than silently discarding it or
     * restoring one that predates it.
     *
     * Returns false when the member is not archived (or no longer is): a live
     * member's consent is an ordinary column write the caller can make itself,
     * and leaving archive is a transition that must go through unarchive().
     *
     * Deliberately not operator-gated: it writes only the consent snapshot and
     * cannot change an archive state or grant door access, so it is not an
     * archive-authority decision. The importers, which own the surrounding
     * permission decision, call it after their own pre-write gate.
     */
    public function recordConsentWhileArchived(GymMember $member, bool $consent): bool
    {
        $persisted = DB::transaction(function () use ($member, $consent): ?GymMember {
            $locked = GymMember::query()
                ->whereKey($member->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof GymMember || ! $locked->is_archived) {
                return null;
            }

            $locked->forceFill(['whatsapp_marketing_snapshot' => $consent])->save();

            return $locked;
        });

        if (! $persisted instanceof GymMember) {
            return false;
        }

        $member->setRawAttributes($persisted->getAttributes(), true);

        return true;
    }

    /**
     * Revoke the member's live biometric credentials as part of archiving.
     *
     * Archiving is an access revocation, and a credential that still opens the
     * door is not revoked — so this cannot be left to the daily
     * `app:cleanup-expired-device-members` sweep alone, which an archived member
     * with a current (or absent) `membership_end` would not even be selected by.
     * The sweep was widened to include archived members as the retry net; this
     * synchronous attempt is the first line.
     *
     * Every status that can represent a live or pending hardware credential is
     * included, not just the active ones. A mid-enrolment placeholder is
     * `is_active = false, sync_status = Pending` with its `user_add` already
     * queued, so an `is_active` filter would skip it and let the ADMS reconciler
     * promote it back to a live credential after the member is archived — the
     * exact state this method exists to prevent.
     *
     * Runs after the archive transaction commits on every operator path — the
     * archive write is its own transaction, so a per-template failure here cannot
     * roll it back. (A caller that already holds an outer transaction, such as the
     * CSV importer, keeps its own boundary and this runs inside it.) Delegates the
     * whole erasure decision — hardware or provider, sibling retention, attempt-cap
     * re-drive — to {@see BiometricDeProvisioner}, the single owner the GDPR
     * deletion service and the sweep use, so those rules cannot drift. The
     * de-provisioner itself loads the device relation it drives the erase through,
     * so a caller that forgets to eager-load cannot turn the erase into a
     * lazy-loading violation. Each template is compensated independently; a
     * failure is logged rather than swallowed, and a completed revocation is
     * logged with the per-outcome counts.
     */
    private function revokeBiometricCredentials(GymMember $member): void
    {
        $templates = BiometricTemplate::query()
            // Batch optimisation only: `BiometricDeProvisioner::deprovision()` calls
            // `loadMissing('enrolledByDevice')` and is the mechanism that keeps this relation
            // from lazy-loading (which throws outside production) when a caller forgets it.
            ->with(['enrolledByDevice'])
            ->where('gym_id', $member->gym_id)
            ->where('gym_member_id', $member->getKey())
            ->whereIn('sync_status', BiometricTemplateSyncStatusEnum::liveOrPendingCredentialStates())
            ->get();

        if ($templates->isEmpty()) {
            return;
        }

        // Resolved lazily rather than injected: this is a rare, post-commit side
        // path, and keeping it out of the constructor leaves the service cheap
        // to construct and to partial-mock.
        $deProvisioner = resolve(BiometricDeProvisioner::class);

        $outcomes = [];
        $failures = 0;

        foreach ($templates as $template) {
            try {
                $outcome = $deProvisioner->deprovision($template);
                $outcomes[$outcome->value] = ($outcomes[$outcome->value] ?? 0) + 1;
            } catch (Throwable $e) {
                $failures++;

                Log::error('MemberArchiveService: failed to revoke a biometric template while archiving', [
                    'member_id' => $member->getKey(),
                    'gym_id' => $member->gym_id,
                    'template_id' => $template->id,
                    'error_type' => $e::class,
                ]);
            }
        }

        Log::info('MemberArchiveService: revoked biometric credentials while archiving', [
            'member_id' => $member->getKey(),
            'gym_id' => $member->gym_id,
            'template_count' => $templates->count(),
            'outcomes' => $outcomes,
            'failures' => $failures,
        ]);
    }

    /**
     * The membership_end cutoff below which a member is archivable.
     *
     * Single derivation on purpose: the batch command reports this exact date to
     * the operator and the action filters on it, so two independent calculations
     * would be able to disagree about which members a run was aimed at.
     */
    public function cutoffDate(int $years = 2): Carbon
    {
        return Date::now()->subYears($years)->startOfDay();
    }

    /**
     * Build a query for members eligible for archiving.
     *
     * Members are archivable when:
     * - They are NOT already archived
     * - Their membership_end is set
     * - Their membership_end is older than the cutoff date
     *
     * Tenancy is explicit in the signature because it is platform-global by
     * default and that is a decision, not an omission. `app:archive-inactive-gym-members`
     * is scheduled once for the whole installation with no tenant context, so a
     * run genuinely has to sweep every gym — passing `null` there is what makes
     * that visible at the call site. The parameter exists so a caller that DOES
     * have a gym (a panel action, an ad-hoc query) has to say which one it means
     * rather than inheriting "all of them" by accident.
     *
     * @return Builder<GymMember>
     */
    public function archivableMembers(int $years = 2, ?int $gymId = null): Builder
    {
        return GymMember::query()
            ->notArchived()
            ->whereNotNull('membership_end')
            ->where('membership_end', '<', $this->cutoffDate($years))
            ->when($gymId !== null, fn (Builder $query): Builder => $query->where('gym_id', $gymId));
    }
}
