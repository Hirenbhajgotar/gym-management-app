<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Biometrics\Services\RedisBiometricCacheService;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupExpiredDeviceMembers extends Command
{
    protected $signature = 'app:cleanup-expired-device-members';

    protected $description = 'Queue ADMS delete commands for members whose membership has expired beyond the grace period';

    public function __construct(
        protected RedisBiometricCacheService $biometricCache
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $admsGraceDays = config()->integer('biometrics.adms.expired_member_grace_days', 10);
        $totalQueued = 0;
        $totalMembers = 0;

        $gyms = Gym::query()->with('settings')->get();

        foreach ($gyms as $gym) {
            $setting = $gym->settings->firstWhere('key', 'grace_period_days');
            $rawSettingVal = $setting ? $setting->value : null;
            $gymGraceDays = is_numeric($rawSettingVal) ? (int) $rawSettingVal : config()->integer('subscription.grace_period_days', 7);

            $totalGraceDays = $gymGraceDays + $admsGraceDays;
            $cutoffDate = now()->subDays($totalGraceDays);

            // Find expired members with active biometric templates on ADMS-enabled devices
            GymMember::query()
                ->where('gym_id', $gym->id)
                ->where('membership_end', '<', $cutoffDate)
                ->whereHas('biometricTemplates', fn (Builder $q) => $q->where('is_active', true))
                ->with([
                    'biometricTemplates' => function (Relation $templateQuery) {
                        $templateQuery->where('is_active', true)
                            ->with('enrolledByDevice');
                    },
                ])
                ->chunkById(100, function ($expiredMembers) use ($gym, &$totalMembers, &$totalQueued, $cutoffDate, $totalGraceDays) {
                    foreach ($expiredMembers as $member) {
                        $memberQueued = 0;
                        $templatesDeactivated = 0;

                        DB::transaction(function () use ($member, $gym, &$memberQueued, &$templatesDeactivated) {
                            foreach ($member->biometricTemplates as $template) {
                                $device = $template->enrolledByDevice;

                                $pin = $template->resolvedCommandPin();

                                if ($device && $device->adms_enabled) {
                                    // Check for existing pending delete command to avoid duplicates
                                    $existingCommand = AdmsDeviceCommand::query()
                                        ->where('device_id', $device->id)
                                        ->where('command_type', 'user_delete')
                                        ->where('command_payload->Pin', $pin)
                                        ->whereIn('status', [AdmsCommandStatusEnum::PENDING, AdmsCommandStatusEnum::SENT])
                                        ->exists();

                                    if (! $existingCommand) {
                                        try {
                                            AdmsDeviceCommand::query()->create([
                                                'gym_id' => $gym->id,
                                                'device_id' => $device->id,
                                                'command_type' => 'user_delete',
                                                'command_payload' => ['Pin' => $pin],
                                                'idempotency_key' => "user_delete:{$device->id}:{$pin}:{$template->id}",
                                                'status' => AdmsCommandStatusEnum::PENDING,
                                            ]);
                                            $memberQueued++;
                                        } catch (UniqueConstraintViolationException) {
                                            // In-flight command already exists for this template
                                        }
                                    }

                                    $template->update([
                                        'is_active' => false,
                                        'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
                                    ]);
                                } elseif ($device || $template->hasCloudProvider()) {
                                    // Device exists (non-ADMS or inactive) or cloud provider template:
                                    // do not claim confirmed deletion; track obligation as PendingDeletion
                                    $template->update([
                                        'is_active' => false,
                                        'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
                                    ]);
                                } else {
                                    // Truly no device and no cloud provider (local software mock)
                                    $template->update([
                                        'is_active' => false,
                                        'sync_status' => BiometricTemplateSyncStatusEnum::Deleted,
                                    ]);
                                }

                                $templatesDeactivated++;
                            }
                        });

                        $totalQueued += $memberQueued;

                        // Invalidate Redis cache after transaction commits
                        $this->biometricCache->invalidateMember($gym->id, $member->id);

                        if ($templatesDeactivated > 0) {
                            $totalMembers++;

                            activity()
                                ->performedOn($member)
                                ->withProperties([
                                    'gym_id' => $gym->id,
                                    'templates_deactivated' => $templatesDeactivated,
                                    'membership_end' => $member->membership_end?->toDateString(),
                                    'cutoff_date' => $cutoffDate->toDateString(),
                                    'grace_days' => $totalGraceDays,
                                ])
                                ->log('Expired member biometric templates deactivated via ADMS cleanup');
                        }
                    }
                });
        }

        $this->info("Cleanup complete: {$totalMembers} expired members processed, {$totalQueued} delete commands queued.");

        Log::info('ADMS: Expired member cleanup complete', [
            'total_members' => $totalMembers,
            'total_commands_queued' => $totalQueued,
        ]);

        return self::SUCCESS;
    }
}
