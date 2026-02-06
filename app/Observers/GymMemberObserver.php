<?php

namespace App\Observers;

use App\Events\Reports\MemberStatsUpdated;
use App\Helpers\ParserHelper;
use App\Models\GymMember;
use App\Models\ReportGoal;
use App\Services\AvatarService;
use App\Services\ReportCacheService;
use Illuminate\Support\Facades\Cache;

class GymMemberObserver
{
    public function __construct(
        protected ReportCacheService $reportCacheService
    ) {}

    /**
     * Handle the GymMember "created" event.
     */
    public function created(GymMember $gymMember): void
    {
        $this->clearCache($gymMember);

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
        $this->clearCache($gymMember);
    }

    /**
     * Handle the GymMember "deleted" event.
     */
    public function deleted(GymMember $gymMember): void
    {
        $this->clearCache($gymMember);
    }

    /**
     * Handle the GymMember "restored" event.
     */
    public function restored(GymMember $gymMember): void
    {
        $this->clearCache($gymMember);
    }

    /**
     * Handle the GymMember "force deleted" event.
     */
    public function forceDeleted(GymMember $gymMember): void
    {
        $this->clearCache($gymMember);
    }

    private function clearCache(GymMember $gymMember): void
    {
        ParserHelper::clearCache(GymMember::NAVIGATION_BADGE_CACHE_KEY . $gymMember->gym_id);
        ParserHelper::clearCache(GymMember::MEMBER_WEIGHT_PROGRESS_CACHE_KEY . $gymMember->id);

        // Invalidate report caches
        $this->reportCacheService->invalidateMemberReports($gymMember->gym_id);
        $this->reportCacheService->invalidateInsights($gymMember->gym_id);

        // Invalidate GoalProgressWidget cache (for members goal tracking)
        Cache::forget(ReportGoal::CACHE_KEY_PREFIX . $gymMember->gym_id);

        // Broadcast real-time event (no toOthers() so current user also receives update)
        broadcast(new MemberStatsUpdated($gymMember->gym_id));
    }
}
