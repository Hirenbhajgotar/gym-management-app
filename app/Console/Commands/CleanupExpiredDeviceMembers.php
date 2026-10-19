<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Biometrics\Services\BiometricDeProvisioner;
use App\Biometrics\Services\RedisBiometricCacheService;
use App\Enums\BiometricDeProvisionOutcome;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CleanupExpiredDeviceMembers extends Command
{
    protected $signature = 'app:cleanup-expired-device-members';

    protected $description = 'Queue ADMS delete commands for members whose membership has expired or who are archived';

    public function __construct(
        protected RedisBiometricCacheService $biometricCache
    ) {
        parent::__construct();
    }

    public function handle(BiometricDeProvisioner $deProvisioner): int
    {
        $liveTemplateStates = BiometricTemplateSyncStatusEnum::liveCredentialStates();

        $admsGraceDays = config()->integer('biometrics.adms.expired_member_grace_days', 10);
        $totalQueued = 0;
        $totalMembers = 0;
        $totalTemplatesProcessed = 0;
        $totalFailed = 0;

        $gyms = Gym::query()->with('settings')->get();

        foreach ($gyms as $gym) {
            $setting = $gym->settings->firstWhere('key', 'grace_period_days');
            $rawSettingVal = $setting ? $setting->value : null;
            $gymGraceDays = is_numeric($rawSettingVal) ? (int) $rawSettingVal : config()->integer('subscription.grace_period_days', 7);

            $totalGraceDays = $gymGraceDays + $admsGraceDays;
            $cutoffDate = now()->subDays($totalGraceDays);

            // Find expired members — and archived members regardless of expiry —
            // with biometric templates that still represent a live or pending
            // credential on an ADMS-enabled device.
            //
            // Archived members are INCLUDED, deliberately, and independent of
            // membership_end. Archiving is an access revocation — GymMember::
            // canAccessGym() denies an archived member before it reads
            // membership, payment or freeze state, and MembershipGuard::
            // resolveReason() names it member_archived — so the credential that
            // still opens the door has to be erased with the record. This sweep
            // is the retry net for a synchronous revocation that could not be
            // completed at archive time; a manually archived member with a
            // current (or absent) membership_end would otherwise never be
            // revisited. See MemberArchiveService for the single statement of
            // that policy.
            //
            // Template selection is by sync_status, not is_active: a
            // mid-enrolment placeholder is inactive but already has its
            // user_add queued, so an is_active filter would let it survive.
            // De-provisioning is queued and idempotent, and the template row is
            // retained as PendingDeletion until the terminal confirms
            // (biometrics:reconcile-pending-deletions owns that follow-up), so
            // sweeping archived members costs nothing when there is nothing to do.
            GymMember::query()
                ->where('gym_id', $gym->id)
                ->where(function (Builder $query) use ($cutoffDate): void {
                    $query->where('membership_end', '<', $cutoffDate)
                        ->orWhere('is_archived', true);
                })
                ->whereHas('biometricTemplates', fn (Builder $q) => $q->whereIn('sync_status', $liveTemplateStates))
                ->with([
                    'biometricTemplates' => function (Relation $templateQuery) use ($liveTemplateStates): void {
                        $templateQuery->whereIn('sync_status', $liveTemplateStates)
                            ->with('enrolledByDevice');
                    },
                ])
                ->chunkById(100, function ($expiredMembers) use ($gym, $deProvisioner, &$totalMembers, &$totalQueued, &$totalTemplatesProcessed, &$totalFailed, $cutoffDate, $totalGraceDays) {
                    foreach ($expiredMembers as $member) {
                        $memberQueued = 0;
                        $templatesProcessed = 0;

                        try {
                            DB::transaction(function () use ($member, $deProvisioner, &$memberQueued, &$templatesProcessed): void {
                                foreach ($member->biometricTemplates as $template) {
                                    // The null-device park-vs-delete decision lives in
                                    // the de-provisioner, so this loop does not
                                    // re-implement it.
                                    $outcome = $deProvisioner->deprovision($template);

                                    // `Queued` created a delete command; `ReDriven`
                                    // returned a terminal one to the queue. Both are a
                                    // queued delete as far as the operator is concerned.
                                    if (in_array($outcome, [
                                        BiometricDeProvisionOutcome::Queued,
                                        BiometricDeProvisionOutcome::ReDriven,
                                    ], true)) {
                                        $memberQueued++;
                                    }

                                    $templatesProcessed++;
                                }
                            });
                        } catch (Throwable $e) {
                            // Contain a single member's failure. Without this, one
                            // QueryException inside the chunk callback rolls that member
                            // back, exits the command non-zero and skips every remaining
                            // member in this gym and every later gym. Archiving and
                            // de-provisioning are idempotent, so the member is simply
                            // re-selected on the next run.
                            $totalFailed++;

                            Log::error('ADMS: expired member cleanup failed for one member; continuing', [
                                'gym_id' => $gym->id,
                                'member_id' => $member->id,
                                'error_type' => $e::class,
                            ]);

                            continue;
                        }

                        $totalTemplatesProcessed += $templatesProcessed;
                        $totalQueued += $memberQueued;

                        // Invalidate Redis cache after transaction commits
                        $this->biometricCache->invalidateMember($gym->id, $member->id);

                        if ($templatesProcessed > 0) {
                            $totalMembers++;

                            activity()
                                ->performedOn($member)
                                ->withProperties([
                                    'gym_id' => $gym->id,
                                    'templates_processed' => $templatesProcessed,
                                    'delete_commands_queued' => $memberQueued,
                                    'membership_end' => $member->membership_end?->toDateString(),
                                    'is_archived' => $member->is_archived,
                                    'cutoff_date' => $cutoffDate->toDateString(),
                                    'grace_days' => $totalGraceDays,
                                ])
                                ->log('Expired or archived member biometric credentials de-provisioned via ADMS cleanup');
                        }
                    }
                });
        }

        $this->info("Cleanup complete: {$totalMembers} expired or archived members processed, {$totalTemplatesProcessed} template(s) processed, {$totalQueued} delete command(s) queued.");

        if ($totalFailed > 0) {
            $this->warn("{$totalFailed} member(s) could not be de-provisioned this run; they will be retried on the next sweep.");
        }

        Log::info('ADMS: Expired member cleanup complete', [
            'total_members' => $totalMembers,
            'total_templates_processed' => $totalTemplatesProcessed,
            'total_commands_queued' => $totalQueued,
            'total_failed' => $totalFailed,
        ]);

        return self::SUCCESS;
    }
}
