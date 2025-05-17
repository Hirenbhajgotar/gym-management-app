<?php

namespace App\Observers;

use App\Models\GymMember;
use App\Services\AvatarService;
use Illuminate\Support\Facades\Log;

class GymMemberObserver
{
    /**
     * Handle the GymMember "created" event.
     */
    public function created(GymMember $gymMember): void
    {

        if (!$gymMember->profile_photo) {
            $profile_photo = AvatarService::generate($gymMember->name, background: 'random');
            $gymMember->profile_photo = $profile_photo;
            $gymMember->save(); // Save the updated profile_photo
        }
    }

    /**
     * Handle the GymMember "updated" event.
     */
    public function updated(GymMember $gymMember): void
    {
        //
    }

    /**
     * Handle the GymMember "deleted" event.
     */
    public function deleted(GymMember $gymMember): void
    {
        //
    }

    /**
     * Handle the GymMember "restored" event.
     */
    public function restored(GymMember $gymMember): void
    {
        //
    }

    /**
     * Handle the GymMember "force deleted" event.
     */
    public function forceDeleted(GymMember $gymMember): void
    {
        //
    }
}
