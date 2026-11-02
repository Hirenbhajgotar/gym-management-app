<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Biometrics\Services\BiometricDeProvisioner;
use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDeProvisionOutcome;
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

    public function handle(BiometricDeProvisioner $deProvisioner): int
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
        $unresolvableCount = 0;
        $retryableCount = 0;
        $alreadyQueuedCount = 0;
        $deviceUnavailableCount = 0;

        $query->chunkById($limit, function ($pendingTemplates) use ($deProvisioner, &$reconciledCount, &$requeuedCount, &$deadLetterCount, &$deferredSiblingCount, &$exhaustedCount, &$unresolvableCount, &$retryableCount, &$alreadyQueuedCount, &$deviceUnavailableCount): void {
            foreach ($pendingTemplates as $template) {
                try {
                    // A hardware row whose delete command has just exhausted its delivery
                    // budget is dead-lettered rather than re-driven again immediately, so a
                    // deterministic firmware fault cannot spin hardware writes every hour.
                    // A re-enrolment on the same PIN is a fresh obligation, though: a new
                    // credential the terminal accepted after the last consumed epoch must be
                    // re-driven promptly rather than waiting out the dead-letter window, and
                    // the de-provisioner then consumes the new epoch.
                    if (! $template->hasCloudProvider()
                        && $template->enrolled_by_device_id !== null
                        && $this->isRecentlyExhausted(
                            $template->enrolled_by_device_id,
                            $template->resolvedCommandPin(),
                            $template->gym_id
                        )
                        && ! $deProvisioner->hasUnconsumedHardwareCredential(
                            $template->enrolled_by_device_id,
                            $template->resolvedCommandPin(),
                            $template->gym_id
                        )) {
                        Log::warning('ReconcilePendingBiometricDeletions: hardware delete attempts exhausted; dead-lettered', [
                            'template_id' => $template->id,
                            'device_id' => $template->enrolled_by_device_id,
                            'member_id' => $template->gym_member_id,
                            'attempt_cap' => AdmsCommandKey::maxAttempts(),
                        ]);

                        // Park it as an obligation, exactly like the de-provisioner's
                        // `Exhausted` arm: without the flag the row stayed at
                        // `PendingDeletion` with no operator surface and no automated path.
                        $template->parkAsUnresolvableObligation('hardware delete attempts exhausted', [
                            'device_id' => $template->enrolled_by_device_id,
                            'attempt_cap' => AdmsCommandKey::maxAttempts(),
                        ]);

                        $deadLetterCount++;

                        continue;
                    }

                    // One call owns the whole decision — cloud vs hardware, null device,
                    // sibling retention, attempt-cap re-drive — so this command can never
                    // disagree with the archive service or the sweeps about which path a
                    // row takes. See BiometricDeProvisioner.
                    $outcome = $deProvisioner->deprovision($template);

                    match ($outcome) {
                        BiometricDeProvisionOutcome::CloudErased,
                        BiometricDeProvisionOutcome::NotDelivered => $reconciledCount++,
                        BiometricDeProvisionOutcome::Queued,
                        BiometricDeProvisionOutcome::ReDriven => $requeuedCount++,
                        BiometricDeProvisionOutcome::DeferredToSibling => $deferredSiblingCount++,
                        BiometricDeProvisionOutcome::Exhausted => $exhaustedCount++,
                        BiometricDeProvisionOutcome::CloudUnresolvable,
                        BiometricDeProvisionOutcome::DeviceRemoved,
                        BiometricDeProvisionOutcome::UnaddressablePin => $unresolvableCount++,
                        BiometricDeProvisionOutcome::CloudRetry => $retryableCount++,
                        // A live (PENDING/SENT) delete command already exists. A SENT command at
                        // the delivery cap is not re-leasable, and `app:reclaim-stale-device-commands`
                        // owns that case: it fails the stale lease within its 10-minute cadence,
                        // after which the `isRecentlyExhausted()` guard above dead-letters it.
                        BiometricDeProvisionOutcome::AlreadyQueued => $alreadyQueuedCount++,
                        BiometricDeProvisionOutcome::DeviceUnavailable => $deviceUnavailableCount++,
                    };
                } catch (Throwable $e) {
                    Log::warning('Failed to reconcile pending template deletion', [
                        'template_id' => $template->id,
                        'error_type' => $e::class,
                    ]);
                }
            }
        });

        $this->info("Reconciliation complete. {$reconciledCount} template(s) deleted, {$requeuedCount} command(s) queued.");

        // One line per non-zero bucket. These used to be concatenated into a single summary
        // line, which the console formatter wraps at the terminal width — splitting the very
        // counters an operator has to read (and making them unassertable).
        if ($deadLetterCount > 0) {
            $this->warn("{$deadLetterCount} template(s) DEAD-LETTERED (retries exhausted, manual intervention required).");
        }

        if ($exhaustedCount > 0) {
            $this->warn("{$exhaustedCount} template(s) still ERASURE-OUTSTANDING past the delivery cap (manual intervention required).");
        }

        if ($unresolvableCount > 0) {
            $this->warn("{$unresolvableCount} template(s) moved to UNRESOLVABLE (no provider reference, or provider refusals past the biometrics.cloud_erasure_cooldown_hours cooldown; operator required).");
        }

        if ($deferredSiblingCount > 0) {
            $this->info("{$deferredSiblingCount} template(s) deferred because a sibling template on the same device still needs the hardware credential.");
        }

        if ($retryableCount > 0) {
            $this->info("{$retryableCount} template(s) still awaiting provider confirmation.");
        }

        if ($alreadyQueuedCount > 0) {
            $this->info("{$alreadyQueuedCount} template(s) already had a live delete command.");
        }

        if ($deviceUnavailableCount > 0) {
            $this->info("{$deviceUnavailableCount} template(s) waiting on an unavailable device.");
        }

        return self::SUCCESS;
    }

    /**
     * True when a `user_delete` for this PIN and gym exhausted its delivery budget recently.
     *
     * The cooldown stops the reconciler re-driving a command that has just failed, so a
     * deterministic firmware fault cannot spin hardware writes every hour. A lease that
     * timed out while the terminal was offline is excluded: the terminal never rejected the
     * command, so it is a transient outage the de-provisioner re-drives with a fresh budget,
     * not an abandoned erasure.
     *
     * The lookup is scoped by `gym_id` as well as device: device ids are not guaranteed
     * globally unique across a restored or sharded database, and a cross-tenant command must
     * never make this gym's template look dead-lettered.
     */
    protected function isRecentlyExhausted(int $deviceId, string $pin, int $gymId): bool
    {
        return AdmsDeviceCommand::query()
            ->where('gym_id', $gymId)
            ->where('device_id', $deviceId)
            ->where('command_type', 'user_delete')
            ->where('command_payload->Pin', $pin)
            ->where('status', AdmsCommandStatusEnum::FAILED)
            // Derived from the one dead-letter definition: exhausted at the cap and not a
            // transient timeout. A second hand-written predicate here was a future drift.
            ->deadLettered()
            ->where('updated_at', '>', now()->subHours(config()->integer('biometrics.adms.retry_cooldown_hours', 24)))
            ->exists();
    }
}
