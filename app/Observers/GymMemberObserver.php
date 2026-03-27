<?php

namespace App\Observers;

use App\Events\Reports\MemberStatsUpdated;
use App\Events\Reports\TrainerStatsUpdated;
use App\Helpers\ParserHelper;
use App\Jobs\SendBiometricConsentEmail;
use App\Models\GymMember;
use App\Models\ReportGoal;
use App\Services\AvatarService;
use App\Services\DashboardCacheService;
use App\Services\ReportCacheService;
use Illuminate\Support\Facades\Cache;

class GymMemberObserver
{
    public function __construct(
        protected ReportCacheService $reportCacheService,
        protected DashboardCacheService $dashboardCacheService
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

        // Auto-send biometric consent email
        // Only if member has an email address
        if (! empty($gymMember->email)) {
            SendBiometricConsentEmail::dispatch($gymMember)
                ->delay(now()->addMinutes(2));
            // 2 minute delay — gives time for any post-create hooks to complete
        }
    }

    /**
     * Handle the GymMember "updated" event.
     */
    public function updated(GymMember $gymMember): void
    {
        $this->clearCache($gymMember);

        // Check if trainer_id was changed (member assigned/unassigned to trainer)
        if ($gymMember->isDirty('trainer_id') || $gymMember->wasChanged('trainer_id')) {
            $this->handleTrainerAssignmentChange($gymMember);
        }
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

        // Invalidate dashboard widget caches
        $this->dashboardCacheService->invalidateMemberWidgets($gymMember->gym_id);

        // Broadcast real-time event
        broadcast(new MemberStatsUpdated($gymMember->gym_id));
    }

    /**
     * Handle trainer assignment changes for a member.
     */
    private function handleTrainerAssignmentChange(GymMember $gymMember): void
    {
        // Invalidate trainer report caches
        $this->reportCacheService->invalidateTrainerReports($gymMember->gym_id);

        // Broadcast trainer stats updated event
        broadcast(new TrainerStatsUpdated($gymMember->gym_id));
    }
}
