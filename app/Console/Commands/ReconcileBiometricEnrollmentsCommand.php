<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricTemplate;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

class ReconcileBiometricEnrollmentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'biometrics:reconcile-enrollments
                            {--dry-run : Report out-of-sync enrollments without creating commands}
                            {--gym= : Reconcile for a specific gym ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile Synced and active biometric templates that lack an active or terminal user_add command';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $rawGym = $this->option('gym');
        $gymId = null;
        if ($rawGym !== null) {
            $validatedGym = filter_var($rawGym, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($validatedGym === false) {
                $this->error('--gym must be a positive integer gym ID.');

                return self::FAILURE;
            }
            $gymId = $validatedGym;
        }

        $this->info('Starting biometric enrollment reconciliation...');
        if ($dryRun) {
            $this->warn('DRY RUN MODE — no commands will be queued.');
        }

        $query = BiometricTemplate::query()
            ->whereIn('sync_status', [
                BiometricTemplateSyncStatusEnum::Synced,
                BiometricTemplateSyncStatusEnum::Propagated,
            ])
            ->where('is_active', true)
            ->whereNotNull('enrolled_by_device_id')
            ->when($gymId, fn (Builder $q): Builder => $q->where('gym_id', $gymId))
            ->with(['gymMember', 'enrolledByDevice']);

        $totalCount = (clone $query)->count();
        $this->info("Examining {$totalCount} active template(s) (Synced/Propagated)...");

        $reconciledCount = 0;
        $skippedConsentCount = 0;
        $skippedDeviceCount = 0;
        $skippedMembershipCount = 0;
        $skippedInFlightDeleteCount = 0;
        $skippedDeadLetterCount = 0;
        $maxAttempts = AdmsCommandKey::maxAttempts();

        $query->chunkById(100, function ($templates) use (
            $dryRun,
            &$reconciledCount,
            &$skippedConsentCount,
            &$skippedDeviceCount,
            &$skippedMembershipCount,
            &$skippedInFlightDeleteCount,
            &$skippedDeadLetterCount,
            $maxAttempts
        ): void {
            foreach ($templates as $template) {
                $member = $template->gymMember;
                $device = $template->enrolledByDevice;

                if ($member === null || ! $member->hasActiveBiometricConsent()) {
                    $skippedConsentCount++;

                    continue;
                }

                // Skip archived members or members with expired memberships
                if ($member->is_archived || ($member->membership_end !== null && $member->membership_end->isPast())) {
                    $skippedMembershipCount++;

                    continue;
                }

                if ($device === null || ! $device->isActive() || ! $device->adms_enabled) {
                    $skippedDeviceCount++;

                    continue;
                }

                $pin = BiometricTemplate::resolveCommandPin($template->template_ref, $member->id);

                // Do not queue user_add if a user_delete is pending or in-flight for this pin
                $hasInFlightDelete = AdmsDeviceCommand::query()
                    ->where('device_id', $device->id)
                    ->where('command_type', 'user_delete')
                    ->where('command_payload->Pin', $pin)
                    ->whereIn('status', [AdmsCommandStatusEnum::PENDING, AdmsCommandStatusEnum::SENT])
                    ->exists();

                if ($hasInFlightDelete) {
                    $skippedInFlightDeleteCount++;

                    continue;
                }

                // Check latest user_add command health.
                // PENDING is in-flight. SENT is healthy ONLY if under the delivery cap.
                // ACKNOWLEDGED is healthy. Re-queue if missing, FAILED, CANCELLED, or attempt-exhausted SENT.
                $latestCommand = AdmsDeviceCommand::query()
                    ->where('device_id', $device->id)
                    ->where('command_type', 'user_add')
                    ->where('command_payload->Pin', $pin)
                    ->latest('id')
                    ->first();

                if ($latestCommand !== null) {
                    if ($latestCommand->status === AdmsCommandStatusEnum::PENDING) {
                        continue;
                    }

                    // A SENT command at the delivery cap is exhausted, not in-flight
                    if ($latestCommand->status === AdmsCommandStatusEnum::SENT && $latestCommand->attempt_count < $maxAttempts) {
                        continue;
                    }

                    if ($latestCommand->status === AdmsCommandStatusEnum::ACKNOWLEDGED) {
                        continue;
                    }
                }

                // Check if a reconcile command already failed or exhausted attempts for this template
                $idempotencyKey = AdmsDeviceCommand::idempotencyKeyFor(
                    $device->gym_id,
                    $device->id,
                    $member->id,
                    $template->id,
                    AdmsCommandKey::STAGE_RECONCILE
                );

                $existingReconcile = AdmsDeviceCommand::query()
                    ->where('device_id', $device->id)
                    ->where(function (\Illuminate\Contracts\Database\Query\Builder $q) use ($idempotencyKey, $pin): void {
                        $q->where('idempotency_key', $idempotencyKey)
                            ->orWhere(function (\Illuminate\Contracts\Database\Query\Builder $q2) use ($pin): void {
                                $q2->whereNull('idempotency_key')
                                    ->where('command_type', 'user_add')
                                    ->where('command_payload->Pin', $pin)
                                    ->where('status', AdmsCommandStatusEnum::FAILED);
                            });
                    })
                    ->latest('id')
                    ->first();

                if ($existingReconcile !== null && $existingReconcile->attempt_count >= $maxAttempts) {
                    // Dead-letter: avoid unbounded hardware write spam
                    Log::warning('ReconcileBiometricEnrollmentsCommand: skipping template after failed reconciliation attempts', [
                        'template_id' => $template->id,
                        'device_id' => $device->id,
                        'member_id' => $member->id,
                        'command_id' => $existingReconcile->id,
                    ]);
                    $skippedDeadLetterCount++;

                    continue;
                }

                $this->line("Reconciling template #{$template->id} for member #{$member->id} on device #{$device->id} ({$device->device_name})...");

                if ($dryRun) {
                    $reconciledCount++;
                } else {
                    if ($existingReconcile !== null && $existingReconcile->status === AdmsCommandStatusEnum::FAILED) {
                        $existingReconcile->update([
                            'status' => AdmsCommandStatusEnum::PENDING,
                            'idempotency_key' => $idempotencyKey,
                            'sent_at' => null,
                            'acknowledged_at' => null,
                            'leased_at' => null,
                            'expires_at' => null,
                            'device_return_code' => null,
                        ]);

                        $reconciledCount++;

                        Log::info('ReconcileBiometricEnrollmentsCommand: re-drove failed user_add command', [
                            'gym_id' => $device->gym_id,
                            'device_id' => $device->id,
                            'member_id' => $member->id,
                            'template_id' => $template->id,
                            'command_id' => $existingReconcile->id,
                        ]);
                    } elseif ($existingReconcile === null) {
                        try {
                            AdmsDeviceCommand::query()->create([
                                'gym_id' => $device->gym_id,
                                'device_id' => $device->id,
                                'command_type' => 'user_add',
                                'command_payload' => [
                                    'Pin' => $pin,
                                    'Name' => $member->name,
                                ],
                                'status' => AdmsCommandStatusEnum::PENDING,
                                'idempotency_key' => $idempotencyKey,
                            ]);

                            $reconciledCount++;

                            Log::info('ReconcileBiometricEnrollmentsCommand: queued replacement user_add command', [
                                'gym_id' => $device->gym_id,
                                'device_id' => $device->id,
                                'member_id' => $member->id,
                                'template_id' => $template->id,
                            ]);
                        } catch (UniqueConstraintViolationException $e) {
                            Log::warning('ReconcileBiometricEnrollmentsCommand: duplicate user_add command collision', [
                                'template_id' => $template->id,
                                'device_id' => $device->id,
                                'idempotency_key' => $idempotencyKey,
                            ]);
                        }
                    }
                }
            }
        });

        $this->newLine();
        $verb = $dryRun ? 'candidate command(s) would be queued' : 'command(s) queued';
        $this->info("Reconciliation complete: {$reconciledCount} {$verb} (skipped: {$skippedConsentCount} missing consent, {$skippedMembershipCount} archived/expired, {$skippedInFlightDeleteCount} pending deletion, {$skippedDeviceCount} inactive device, {$skippedDeadLetterCount} dead-lettered).");

        return self::SUCCESS;
    }
}
