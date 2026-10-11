<?php

declare(strict_types=1);

namespace App\Observers;

use App\Biometrics\Services\RedisBiometricCacheService;
use App\Events\Reports\MemberStatsUpdated;
use App\Events\Reports\TrainerStatsUpdated;
use App\Helpers\ParserHelper;
use App\Jobs\SendBiometricConsentNotificationJob;
use App\Models\GymMember;
use App\Models\ReportGoal;
use App\Services\AvatarService;
use App\Services\DashboardCacheService;
use App\Services\ReportCacheService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Cache;

class GymMemberObserver implements ShouldHandleEventsAfterCommit
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

        // Onboarding consent email. Gated on an active biometric device so a gym
        // that does not run biometric check-in never asks a new member for
        // consent it will never use (REQ-10/REQ-11 on the live path).
        if (filled($gymMember->email) && $gymMember->gym?->hasActiveBiometricDevice() === true) {
            // Eager-load gym relation to prevent N+1 when Mailable accesses $gymMember->gym->name
            dispatch(new SendBiometricConsentNotificationJob(
                gymMember: $gymMember->load('gym'),
                sendEmail: true,
                sendWhatsApp: false,
            ))
                ->delay(now()->addMinutes(config('biometrics.biometric_consent_email_delay')))
                ->afterCommit();
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

        // Invalidate biometric access summary cache
        resolve(RedisBiometricCacheService::class)->invalidateMember(
            $gymMember->gym_id,
            $gymMember->id
        );

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
