<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Biometrics\Services\BiometricDataDeletionService;
use App\Biometrics\Services\BiometricDeProvisioner;
use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDeProvisionOutcome;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricTemplate;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcilePendingBiometricDeletionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'biometrics:reconcile-pending-deletions {--limit=100 : Maximum records to process per batch} {--gym= : Optional Gym ID to scope reconciliation to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-drive and reconcile biometric templates awaiting hardware or provider deletion';

    public function handle(BiometricDataDeletionService $deletionService, BiometricDeProvisioner $deProvisioner): int
    {
        $gymId = $this->option('gym');
        if ($gymId !== null) {
            $gymId = filter_var($gymId, FILTER_VALIDATE_INT);
            if ($gymId === false || $gymId <= 0) {
                $this->error('The --gym option must be a positive integer.');

                return self::FAILURE;
            }
        }

        $limit = (int) $this->option('limit');
        if ($limit <= 0) {
            $limit = 100;
        }

        $query = BiometricTemplate::query()
            ->where('sync_status', BiometricTemplateSyncStatusEnum::PendingDeletion)
            ->when($gymId, fn (Builder $q): Builder => $q->where('gym_id', $gymId))
            ->with(['enrolledByDevice', 'gymMember']);

        $totalCount = (clone $query)->count();
        if ($totalCount === 0) {
            $this->info('No biometric templates pending deletion.');

            return self::SUCCESS;
        }

        $reconciledCount = 0;
        $requeuedCount = 0;
        $deadLetterCount = 0;
        $deferredSiblingCount = 0;
        $exhaustedCount = 0;

        $query->chunkById($limit, function ($pendingTemplates) use ($deletionService, $deProvisioner, &$reconciledCount, &$requeuedCount, &$deadLetterCount, &$deferredSiblingCount, &$exhaustedCount): void {
            foreach ($pendingTemplates as $template) {
                try {
                    if ($template->hasCloudProvider()) {
                        if ($deletionService->deleteSingleTemplate($template)) {
                            $reconciledCount++;
                        }

                        continue;
                    }

                    if ($template->enrolled_by_device_id === null) {
                        $template->update([
                            'is_active' => false,
                            'sync_status' => BiometricTemplateSyncStatusEnum::Deleted,
                        ]);
                        $reconciledCount++;

                        continue;
                    }

                    $device = $template->enrolledByDevice;

                    if ($device === null) {
                        Log::warning('ReconcilePendingBiometricDeletions: Device record missing for pending deletion template; hardware clearance cannot be verified', [
                            'template_id' => $template->id,
                            'enrolled_by_device_id' => $template->enrolled_by_device_id,
                        ]);

                        continue;
                    }

                    if (! $device->adms_enabled || $device->status !== BiometricDeviceStatusEnum::Active) {
                        Log::info('ReconcilePendingBiometricDeletions: Device inactive or non-ADMS; awaiting terminal online status', [
                            'template_id' => $template->id,
                            'device_id' => $device->id,
                            'status' => $device->status->value,
                        ]);

                        continue;
                    }

                    if ($this->isRecentlyExhausted($device->id, $template->resolvedCommandPin())) {
                        Log::warning('ReconcilePendingBiometricDeletions: hardware delete attempts exhausted; dead-lettered', [
                            'template_id' => $template->id,
                            'device_id' => $device->id,
                            'member_id' => $template->gym_member_id,
                            'attempt_cap' => AdmsCommandKey::maxAttempts(),
                        ]);
                        $deadLetterCount++;

                        continue;
                    }

                    $outcome = $deProvisioner->deprovision($template);

                    match ($outcome) {
                        BiometricDeProvisionOutcome::Queued,
                        BiometricDeProvisionOutcome::ReDriven => $requeuedCount++,
                        BiometricDeProvisionOutcome::DeferredToSibling => $deferredSiblingCount++,
                        BiometricDeProvisionOutcome::NotDelivered => $reconciledCount++,
                        BiometricDeProvisionOutcome::Exhausted => $exhaustedCount++,
                        default => null,
                    };
                } catch (Throwable $e) {
                    Log::warning('Failed to reconcile pending template deletion', [
                        'template_id' => $template->id,
                        'error_type' => $e::class,
                    ]);
                }
            }
        });

        $this->info(
            "Reconciliation complete. {$reconciledCount} template(s) deleted, {$requeuedCount} command(s) queued."
            . ($deadLetterCount > 0 ? " {$deadLetterCount} template(s) DEAD-LETTERED (retries exhausted, manual intervention required)." : '')
            . ($exhaustedCount > 0 ? " {$exhaustedCount} template(s) still ERASURE-OUTSTANDING past the delivery cap (manual intervention required)." : '')
            . ($deferredSiblingCount > 0 ? " {$deferredSiblingCount} template(s) deferred because a sibling template on the same device still needs the hardware credential." : '')
        );

        return self::SUCCESS;
    }

    /**
     * True when a `user_delete` for this PIN exhausted its delivery budget recently.
     *
     * The cooldown stops the reconciler re-driving a command that has just failed, so a
     * deterministic firmware fault cannot spin hardware writes every night.
     */
    protected function isRecentlyExhausted(int $deviceId, string $pin): bool
    {
        return AdmsDeviceCommand::query()
            ->where('device_id', $deviceId)
            ->where('command_type', 'user_delete')
            ->where('command_payload->Pin', $pin)
            ->where('status', AdmsCommandStatusEnum::FAILED)
            ->where('attempt_count', '>=', AdmsCommandKey::maxAttempts())
            ->where('updated_at', '>', now()->subHours(config()->integer('biometrics.adms.retry_cooldown_hours', 24)))
            ->exists();
    }
}
