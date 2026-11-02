<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\DefaultRole;
use App\Models\GymMember;
use App\Models\User;

class GymMemberPolicy extends BasePolicy
{
    protected static string $tableName = 'gym_member';

    /**
     * Determine whether the user can archive a gym member.
     *
     * Archiving hides the member from the current roster, so it is gated on its
     * own permission rather than folded into update(): a receptionist who may
     * edit a phone number is not thereby authorised to retire the member.
     *
     * The permission is necessary but not sufficient once a record is in play.
     * $member is the class name when only the bulk-action visibility is being
     * decided (Filament hands the table's model to the gate), and the record
     * itself when a specific member is being checked — in which case the member
     * must also belong to one of the user's gyms. SUPER_ADMIN is exempt from
     * that tenant check because it administers every gym on the platform.
     */
    public function archive(User $user, GymMember|string|null $member = null): bool
    {
        if ($member instanceof GymMember && ! $this->userCanActOnMemberGym($user, $member)) {
            return false;
        }

        return $user->can(static::getPermissionName('archive'));
    }

    /**
     * Determine whether the user can restore an archived gym member.
     *
     * Separate from archive(), and carrying the same two gates: the
     * unarchive_gym_member permission, plus gym ownership of the record itself
     * unless the user is SUPER_ADMIN. Restoring rewrites WhatsApp consent from
     * the archive snapshot, so it is not implied by update() either.
     */
    public function unarchive(User $user, GymMember|string|null $member = null): bool
    {
        if ($member instanceof GymMember && ! $this->userCanActOnMemberGym($user, $member)) {
            return false;
        }

        return $user->can(static::getPermissionName('unarchive'));
    }

    /**
     * Determine whether the user can enroll biometrics for a gym member.
     *
     * Trainers are refused outright; everyone else must satisfy the same
     * gym-ownership rule as archive()/unarchive() and then hold update_gym_member
     * (or one of the desk roles that imply it). Biometrics are not folded into
     * the archive permissions and vice versa.
     */
    public function enrollBiometric(User $user, GymMember|string|null $member = null): bool
    {
        if ($user->hasRole(DefaultRole::TRAINER->value)) {
            return false;
        }

        if ($member instanceof GymMember && ! $this->userCanActOnMemberGym($user, $member)) {
            return false;
        }

        return $user->can(static::getPermissionName('update')) || $user->hasAnyRole([
            DefaultRole::SUPER_ADMIN->value,
            DefaultRole::ADMIN->value,
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
            DefaultRole::RECEPTIONIST->value,
        ]);
    }

    /**
     * Determine whether the user can attest that a biometric credential was physically erased.
     *
     * This is the compliance exit for an erasure the system cannot discharge itself, and it
     * writes a terminal `Deleted` state while clearing the only marker that the credential
     * may still be live. It is therefore deliberately narrower than enrollBiometric(): a
     * receptionist may enrol a member, but may not sign off an erasure. The record-aware
     * form also requires the member to belong to one of the user's gyms.
     */
    public function dischargeBiometricErasure(User $user, GymMember|string|null $member = null): bool
    {
        if ($member instanceof GymMember && ! $this->userCanActOnMemberGym($user, $member)) {
            return false;
        }

        return $user->hasAnyRole([
            DefaultRole::SUPER_ADMIN->value,
            DefaultRole::ADMIN->value,
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
        ]);
    }

    /**
     * Verify the member belongs to one of the gyms the user is attached to.
     *
     * Tenant isolation defence-in-depth: the panel already scopes the roster to
     * the selected gym, but a policy that only reads a permission would still
     * let someone holding archive_gym_member for gym A act on a member of gym
     * B. SUPER_ADMIN bypasses the check because it owns the whole platform.
     *
     * Shared by every record-aware member ability so the rule is stated once.
     */
    private function userCanActOnMemberGym(User $user, GymMember $member): bool
    {
        return $this->userCanActOnGym($user, $member->gym_id);
    }
}
