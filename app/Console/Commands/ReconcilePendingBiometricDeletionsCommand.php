<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Biometrics\Services\BiometricDataDeletionService;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricTemplate;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcilePendingBiometricDeletionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'biometrics:reconcile-pending-deletions {--limit=100 : Maximum records to process per run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-drive and reconcile biometric templates awaiting hardware or provider deletion';

    public function handle(BiometricDataDeletionService $deletionService): int
    {
        $limit = (int) $this->option('limit');
        if ($limit <= 0) {
            $limit = 100;
        }

        // Query templates in PendingDeletion status using the (gym_id, sync_status) index with deterministic ordering
        $pendingTemplates = BiometricTemplate::query()
            ->where('sync_status', BiometricTemplateSyncStatusEnum::PendingDeletion)
            ->with(['enrolledByDevice', 'gymMember'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($pendingTemplates->isEmpty()) {
            $this->info('No biometric templates pending deletion.');

            return self::SUCCESS;
        }

        $reconciledCount = 0;
        $requeuedCount = 0;

        foreach ($pendingTemplates as $template) {
            try {
                // If it's a cloud provider template, retry deletion via provider
                if ($template->hasCloudProvider()) {
                    $success = $deletionService->deleteSingleTemplate($template);
                    if ($success) {
                        $reconciledCount++;
                    }

                    continue;
                }

                $device = $template->enrolledByDevice;

                // If device is active and ADMS-enabled, verify command exists or queue one
                if ($device && $device->adms_enabled && $device->status === BiometricDeviceStatusEnum::Active) {
                    $pin = $template->resolvedCommandPin();

                    $existingCommand = AdmsDeviceCommand::query()
                        ->where('device_id', $device->id)
                        ->where('command_type', 'user_delete')
                        ->where('command_payload->Pin', $pin)
                        ->whereIn('status', [AdmsCommandStatusEnum::PENDING, AdmsCommandStatusEnum::SENT])
                        ->exists();

                    $recentlyExhausted = AdmsDeviceCommand::query()
                        ->where('device_id', $device->id)
                        ->where('command_type', 'user_delete')
                        ->where('command_payload->Pin', $pin)
                        ->where('status', AdmsCommandStatusEnum::FAILED)
                        ->where('attempt_count', '>=', 3)
                        ->where('updated_at', '>', now()->subHours(24))
                        ->exists();

                    if (! $existingCommand && ! $recentlyExhausted) {
                        try {
                            AdmsDeviceCommand::query()->create([
                                'gym_id' => $device->gym_id,
                                'device_id' => $device->id,
                                'command_type' => 'user_delete',
                                'command_payload' => ['Pin' => $pin],
                                'idempotency_key' => "user_delete:{$device->id}:{$pin}:{$template->id}",
                                'status' => AdmsCommandStatusEnum::PENDING,
                            ]);
                            $requeuedCount++;
                        } catch (UniqueConstraintViolationException) {
                            // In-flight command already queued
                        }
                    }
                } elseif (! $device) {
                    if ($template->enrolled_by_device_id === null) {
                        // Truly local mock with no hardware device
                        $template->update([
                            'is_active' => false,
                            'sync_status' => BiometricTemplateSyncStatusEnum::Deleted,
                        ]);
                        $reconciledCount++;
                    } else {
                        // Device row removed from DB; do not falsely attest hardware deletion
                        $template->update(['is_active' => false]);
                        Log::warning('ReconcilePendingBiometricDeletions: Device record missing for pending deletion template; hardware clearance cannot be verified', [
                            'template_id' => $template->id,
                            'enrolled_by_device_id' => $template->enrolled_by_device_id,
                        ]);
                    }
                } elseif ($device && (! $device->adms_enabled || $device->status !== BiometricDeviceStatusEnum::Active)) {
                    Log::info('ReconcilePendingBiometricDeletions: Device inactive or non-ADMS; awaiting terminal online status', [
                        'template_id' => $template->id,
                        'device_id' => $device->id,
                        'status' => $device->status->value,
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning('Failed to reconcile pending template deletion', [
                    'template_id' => $template->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Reconciliation complete. {$reconciledCount} template(s) deleted, {$requeuedCount} command(s) queued.");

        return self::SUCCESS;
    }
}
