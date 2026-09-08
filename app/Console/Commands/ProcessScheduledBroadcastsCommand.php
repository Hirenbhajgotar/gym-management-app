<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\WhatsAppBroadcastRecipientStatusEnum;
use App\Enums\WhatsAppBroadcastStatusEnum;
use App\Helpers\WhatsAppSanitizer;
use App\Jobs\SendBroadcastJob;
use App\Models\WhatsAppBroadcast;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessScheduledBroadcastsCommand extends Command
{
    protected $signature = 'whatsapp:process-scheduled';

    protected $description = 'Process WhatsApp broadcasts that are scheduled and due for sending, and reconcile stuck broadcasts';

    private const MAX_BROADCASTS_PER_RUN = 100;

    public const STUCK_SENDING_THRESHOLD_HOURS = 2;

    /**
     * Execute the console command.
     *
     * Atomically claims each scheduled broadcast (status → 'sending') before
     * dispatching SendBroadcastJob with skipCreditDeduction matching credits_deducted_upfront.
     */
    public function handle(): int
    {
        $broadcasts = WhatsAppBroadcast::query()->where('status', WhatsAppBroadcastStatusEnum::SCHEDULED->value)
            ->where('scheduled_at', '<=', now())
            ->limit(self::MAX_BROADCASTS_PER_RUN)
            ->lazy();

        $processed = 0;

        foreach ($broadcasts as $broadcast) {
            $claimed = WhatsAppBroadcast::query()->where('id', $broadcast->id)
                ->where('status', WhatsAppBroadcastStatusEnum::SCHEDULED->value)
                ->update([
                    'status' => WhatsAppBroadcastStatusEnum::SENDING->value,
                    'started_at' => now(),
                ]);

            if ($claimed === 0) {
                $this->warn("Broadcast #{$broadcast->id} was already claimed by another process, skipping.");

                continue;
            }

            try {
                $skipCreditDeduction = $broadcast->credits_deducted_upfront;
                dispatch(new SendBroadcastJob($broadcast->id, $broadcast->gym_id, skipCreditDeduction: $skipCreditDeduction));

                $this->info("Dispatched broadcast #{$broadcast->id}: {$broadcast->title}");

                Log::info('Scheduled broadcast dispatched', [
                    'broadcast_id' => $broadcast->id,
                    'gym_id' => $broadcast->gym_id,
                    'title' => $broadcast->title,
                    'scheduled_at' => $broadcast->scheduled_at,
                ]);

                $processed++;
            } catch (Exception $e) {
                $broadcast->update(['status' => WhatsAppBroadcastStatusEnum::FAILED->value]);

                $sanitizedError = WhatsAppSanitizer::sanitizeError($e->getMessage());

                Log::error('Failed to dispatch scheduled broadcast', [
                    'broadcast_id' => $broadcast->id,
                    'error' => $sanitizedError,
                ]);

                $this->error("Failed to dispatch broadcast #{$broadcast->id}: {$sanitizedError}");
            }
        }

        if ($processed === 0) {
            $this->info('No scheduled broadcasts to process.');
        } else {
            $this->info("Dispatched {$processed} broadcast(s).");
        }

        $reconciled = $this->reconcileStuckBroadcasts();
        if ($reconciled > 0) {
            $this->info("Reconciled {$reconciled} stuck broadcast(s).");
        }

        return self::SUCCESS;
    }

    /**
     * Reconcile broadcasts that have remained in SENDING state longer than the threshold.
     */
    private function reconcileStuckBroadcasts(): int
    {
        if (! WhatsAppBroadcast::query()->where('status', WhatsAppBroadcastStatusEnum::SENDING->value)->exists()) {
            return 0;
        }

        $stuckThreshold = now()->subHours(self::STUCK_SENDING_THRESHOLD_HOURS);
        $stuckBroadcasts = WhatsAppBroadcast::query()
            ->where('status', WhatsAppBroadcastStatusEnum::SENDING->value)
            ->where(function (Builder $q) use ($stuckThreshold): void {
                $q->where('started_at', '<=', $stuckThreshold)
                    ->orWhere(function (Builder $sub) use ($stuckThreshold): void {
                        $sub->whereNull('started_at')
                            ->where('created_at', '<=', $stuckThreshold);
                    });
            })
            ->limit(self::MAX_BROADCASTS_PER_RUN)
            ->get();

        $reconciled = 0;

        foreach ($stuckBroadcasts as $broadcastRecord) {
            DB::transaction(function () use ($broadcastRecord, &$reconciled): void {
                /** @var WhatsAppBroadcast|null $broadcast */
                $broadcast = WhatsAppBroadcast::query()
                    ->where('id', $broadcastRecord->id)
                    ->where('status', WhatsAppBroadcastStatusEnum::SENDING->value)
                    ->lockForUpdate()
                    ->first();

                if (! $broadcast) {
                    return;
                }

                // Check batch finality: if batch exists and is actively working, skip reconciliation
                if (filled($broadcast->batch_id)) {
                    $batch = Bus::findBatch($broadcast->batch_id);
                    if ($batch && ! $batch->finished() && ! $batch->cancelled() && $batch->pendingJobs > 0) {
                        return;
                    }
                }

                // Mark any remaining pending recipients as failed so counts align
                $pendingUpdated = DB::table('whatsapp_broadcast_recipients')
                    ->where('whatsapp_broadcast_id', $broadcast->id)
                    ->where('status', WhatsAppBroadcastRecipientStatusEnum::PENDING->value)
                    ->update([
                        'status' => WhatsAppBroadcastRecipientStatusEnum::FAILED->value,
                        'failure_reason' => 'Broadcast timed out in SENDING state during reconciliation sweep',
                        'updated_at' => now(),
                    ]);

                if ($pendingUpdated > 0) {
                    $broadcast->increment('failed_count', $pendingUpdated);
                }

                $broadcast->refresh();

                $sent = $broadcast->sent_count;
                $failed = $broadcast->failed_count;
                $target = $broadcast->target_count;

                $status = WhatsAppBroadcastStatusEnum::fromCounts($sent, $failed, $target);

                $broadcast->update([
                    'status' => $status->value,
                    'completed_at' => $status === WhatsAppBroadcastStatusEnum::FAILED ? null : now(),
                ]);

                // Refund failed upfront credits only when credits were deducted upfront
                if ($broadcast->credits_deducted_upfront) {
                    SendBroadcastJob::refundFailedUpfrontCredits($broadcast->gym_id, $broadcast->id, true);
                }

                $stuckMinutes = $broadcast->started_at ? (int) abs(now()->diffInMinutes($broadcast->started_at)) : null;

                Log::warning('Reconciled stuck broadcast', [
                    'broadcast_id' => $broadcast->id,
                    'gym_id' => $broadcast->gym_id,
                    'resolved_status' => $status->value,
                    'sent_count' => $sent,
                    'failed_count' => $failed,
                    'target_count' => $target,
                    'stuck_minutes' => $stuckMinutes,
                    'credits_deducted_upfront' => $broadcast->credits_deducted_upfront,
                ]);

                $reconciled++;
            });
        }

        return $reconciled;
    }
}
