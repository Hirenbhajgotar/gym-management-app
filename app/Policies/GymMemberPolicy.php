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
     * Determine whether the user can enroll biometrics for a gym member.
     */
    public function enrollBiometric(User $user, GymMember|string|null $member = null): bool
    {
        if ($user->hasRole(DefaultRole::TRAINER->value)) {
            return false;
        }

        // Tenant isolation defence-in-depth: verify member belongs to user's gyms
        if ($member instanceof GymMember && ! $user->hasRole(DefaultRole::SUPER_ADMIN->value)) {
            $userGymIds = $user->relationLoaded('gyms')
                ? $user->gyms->pluck('id')->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->all()
                : $user->gyms()->pluck('gyms.id')->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->all();

            if (! in_array($member->gym_id, $userGymIds, true)) {
                return false;
            }
        }

        return $user->can(static::getPermissionName('update')) || $user->hasAnyRole([
            DefaultRole::SUPER_ADMIN->value,
            DefaultRole::ADMIN->value,
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
            DefaultRole::RECEPTIONIST->value,
        ]);
    }
}
