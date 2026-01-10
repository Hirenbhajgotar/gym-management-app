<?php

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\GymMember;
use App\Services\AvatarService;

class GymMemberObserver
{
    /**
     * Handle the GymMember "created" event.
     */
    public function created(GymMember $gymMember): void
    {
        $this->clearCache();

        if (! $gymMember->profile_photo) {
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
        $this->clearCache();
    }

    /**
     * Handle the GymMember "deleted" event.
     */
    public function deleted(GymMember $gymMember): void
    {
        $this->clearCache();
    }

    /**
     * Handle the GymMember "restored" event.
     */
    public function restored(GymMember $gymMember): void
    {
        $this->clearCache();
    }

    /**
     * Handle the GymMember "force deleted" event.
     */
    public function forceDeleted(GymMember $gymMember): void
    {
        $this->clearCache();
    }

    private function clearCache(): void
    {
        if (auth()->check()) {
            ParserHelper::clearCache('gym_member_navigation_badge_count_' . auth()->id());
        }
    }
}
