<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\GymMember;
use App\Models\User;
use App\Notifications\MemberFrozenNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MemberFreezeService
{
    /**
     * Freeze member (manual or date-based)
     */
    public function freeze(
        GymMember $member,
        ?string $reason = null,
        ?int $days = null,
        ?int $frozenById = null,
        bool $extendMembership = false
    ): bool {
        return DB::transaction(function () use ($member, $reason, $days, $frozenById, $extendMembership) {
            /** @var GymMember $member */
            $member = GymMember::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();

            // Reconcile stale freeze state if already marked frozen (suppress inner unfreeze notification)
            if ($member->is_frozen) {
                if ($this->autoUnfreezeIfExpired($member, notify: false)) {
                    $member->refresh();
                }

                if ($member->isFrozen()) {
                    return false;
                }
            }

            if ($days !== null && $days > 0) {
                $days = min(365, $days);
            }

            $oldMembershipEnd = $member->membership_end?->copy();

            $member->is_frozen = true;
            $member->frozen_at = now();
            $member->frozen_reason = $reason;
            $member->frozen_by = $frozenById !== null && $frozenById >= 0 ? $frozenById : null;
            $member->extend_membership = $extendMembership;

            if ($days !== null && $days > 0) {
                $member->frozen_until = now()->addDays($days);

                if ($extendMembership && $member->membership_end) {
                    $minSetting = $member->gym?->getSetting('min_freeze_days', GymMember::DEFAULT_MIN_FREEZE_DAYS);
                    $minFreezeDays = is_numeric($minSetting) ? (int) $minSetting : GymMember::DEFAULT_MIN_FREEZE_DAYS;
                    $maxSettingVal = $member->gym?->getSetting('max_freeze_days', GymMember::DEFAULT_MAX_FREEZE_DAYS);
                    $maxSetting = is_numeric($maxSettingVal) ? (int) $maxSettingVal : GymMember::DEFAULT_MAX_FREEZE_DAYS;
                    $maxFreezeDays = $this->getMaxFreezeExtensionDays($member, $maxSetting);

                    if ($days >= $minFreezeDays) {
                        $effectiveDays = min($days, $maxFreezeDays);
                        $member->membership_end = $member->membership_end->addDays($effectiveDays);
                    }
                }
            } else {
                $member->frozen_until = null;
            }

            $member->save();
            $this->syncLatestPaymentMembershipEnd($member);

            if ($frozenById && function_exists('activity')) {
                activity('gym_member')
                    ->performedOn($member)
                    ->causedBy(User::query()->find($frozenById))
                    ->withProperties([
                        'actor_id' => $frozenById,
                        'days' => $days,
                        'reason' => $reason,
                        'extend_membership' => $extendMembership,
                        'old_membership_end' => $oldMembershipEnd?->toDateString(),
                        'new_membership_end' => $member->membership_end?->toDateString(),
                        'frozen_until' => $member->frozen_until?->toDateString(),
                    ])
                    ->log('Member frozen');
            }

            $notifiableMember = $member;
            $notifyReason = $reason;
            $notifyFrozenUntil = $member->frozen_until ? $member->frozen_until->copy() : null;

            DB::afterCommit(function () use ($notifiableMember, $notifyReason, $notifyFrozenUntil) {
                $notifiableMember->notify(new MemberFrozenNotification(
                    member: $notifiableMember,
                    isFrozen: true,
                    reason: $notifyReason,
                    frozenUntil: $notifyFrozenUntil
                ));
            });

            return true;
        });
    }

    /**
     * Unfreeze member
     */
    public function unfreeze(
        GymMember $member,
        ?int $unfrozenById = null,
        bool $notify = true
    ): bool {
        return DB::transaction(function () use ($member, $unfrozenById, $notify) {
            /** @var GymMember $member */
            $member = GymMember::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();

            if (! $member->is_frozen) {
                return false;
            }

            $oldMembershipEnd = $member->membership_end?->copy();

            if ($member->extend_membership && $member->frozen_at !== null) {
                $minSetting = $member->gym?->getSetting('min_freeze_days', GymMember::DEFAULT_MIN_FREEZE_DAYS);
                $minFreezeDays = is_numeric($minSetting) ? (int) $minSetting : GymMember::DEFAULT_MIN_FREEZE_DAYS;
                $maxSettingVal = $member->gym?->getSetting('max_freeze_days', GymMember::DEFAULT_MAX_FREEZE_DAYS);
                $maxSetting = is_numeric($maxSettingVal) ? (int) $maxSettingVal : GymMember::DEFAULT_MAX_FREEZE_DAYS;
                $maxFreezeDays = $this->getMaxFreezeExtensionDays($member, $maxSetting);

                $actualDaysFrozen = max(0, (int) ceil($member->frozen_at->diffInDays(now())));

                if ($member->frozen_until === null) {
                    // Manual freeze: apply extension if actualDays >= min
                    if ($member->membership_end && $actualDaysFrozen >= $minFreezeDays) {
                        $effectiveDays = min($actualDaysFrozen, $maxFreezeDays);
                        $member->membership_end = $member->membership_end->addDays($effectiveDays);
                    }
                } else {
                    // Fixed freeze: adjust pre-extended days based on actual frozen days (handles early, exact, and late unfreeze)
                    $preExtendedDays = max(0, (int) ceil($member->frozen_at->diffInDays($member->frozen_until)));
                    $preExtendedEffective = $preExtendedDays >= $minFreezeDays ? min($preExtendedDays, $maxFreezeDays) : 0;
                    $actualEffective = $actualDaysFrozen >= $minFreezeDays ? min($actualDaysFrozen, $maxFreezeDays) : 0;

                    $adjustmentDays = $actualEffective - $preExtendedEffective;
                    if ($member->membership_end && $adjustmentDays !== 0) {
                        $member->membership_end = $member->membership_end->addDays($adjustmentDays);
                    }
                }
            }

            $member->is_frozen = false;
            $member->frozen_at = null;
            $member->frozen_until = null;
            $member->frozen_reason = null;
            $member->extend_membership = false;
            $member->save();
            $this->syncLatestPaymentMembershipEnd($member);

            if ($unfrozenById && function_exists('activity')) {
                activity('gym_member')
                    ->performedOn($member)
                    ->causedBy(User::query()->find($unfrozenById))
                    ->withProperties([
                        'actor_id' => $unfrozenById,
                        'old_membership_end' => $oldMembershipEnd?->toDateString(),
                        'new_membership_end' => $member->membership_end?->toDateString(),
                    ])
                    ->log('Member unfrozen');
            }

            if ($notify) {
                $notifiableMember = $member;
                DB::afterCommit(function () use ($notifiableMember) {
                    $notifiableMember->notify(new MemberFrozenNotification(
                        member: $notifiableMember,
                        isFrozen: false
                    ));
                });
            }

            return true;
        });
    }

    /**
     * Persist the unfreeze if a time-based freeze has expired.
     */
    public function autoUnfreezeIfExpired(GymMember $member, bool $notify = true): bool
    {
        $frozenUntil = $member->frozen_until;
        /** @var Carbon|null $frozenUntil */
        if ($member->is_frozen && $frozenUntil !== null && $frozenUntil->isPast()) {
            $this->unfreeze($member, notify: $notify);

            return true;
        }

        return false;
    }

    /**
     * Get maximum allowed freeze extension days considering both gym settings and plan duration
     */
    public function getMaxFreezeExtensionDays(GymMember $member, int $maxSetting): int
    {
        $member->loadMissing('membershipPlan');

        if ($member->membershipPlan && $member->membershipPlan->duration && $member->membershipPlan->duration > 0) {
            $now = now();
            $planDays = (int) $now->diffInDays($now->copy()->addMonths($member->membershipPlan->duration));

            return min($maxSetting, $planDays);
        }

        return $maxSetting;
    }

    /**
     * Sync latest payment membership_end date to match member's membership_end.
     * Note: Syncing latest non-expired payment is intentional as it represents the current active billing term.
     * Uses updateQuietly to avoid triggering MemberPaymentObserver cache invalidation on pure date bookkeeping.
     */
    public function syncLatestPaymentMembershipEnd(GymMember $member): void
    {
        if (! $member->membership_end) {
            return;
        }

        $latestPayment = $member->latestPayment;
        if ($latestPayment && $latestPayment->status !== PaymentStatus::EXPIRED) {
            $latestPayment->updateQuietly([
                'membership_end' => $member->membership_end,
            ]);
        }
    }
}
