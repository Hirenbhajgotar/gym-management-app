<?php

namespace App\Policies;

use App\Enums\DefaultRole;
use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([DefaultRole::GYM_OWNER->value, DefaultRole::MANAGER->value]);
        // return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Lead $lead): bool
    {
        return $user->hasAnyRole([DefaultRole::GYM_OWNER->value, DefaultRole::MANAGER->value]) && $user->gyms->contains($lead->gym_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([DefaultRole::GYM_OWNER->value, DefaultRole::MANAGER->value]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Lead $lead): bool
    {
        return $user->hasAnyRole([DefaultRole::GYM_OWNER->value, DefaultRole::MANAGER->value]) && $user->gyms->contains($lead->gym_id);
        // return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Lead $lead): bool
    {
        return $user->hasAnyRole([DefaultRole::GYM_OWNER->value, DefaultRole::MANAGER->value]) && $user->gyms->contains($lead->gym_id);
        // return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Lead $lead): bool
    {
        return $user->hasAnyRole([DefaultRole::GYM_OWNER->value, DefaultRole::MANAGER->value]) && $user->gyms->contains($lead->gym_id);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Lead $lead): bool
    {
        return $user->hasAnyRole([DefaultRole::GYM_OWNER->value, DefaultRole::MANAGER->value]) && $user->gyms->contains($lead->gym_id);
    }
}
