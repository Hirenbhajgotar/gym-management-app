<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\WhatsAppBroadcastRecipientStatusEnum;
use App\Enums\WhatsAppBroadcastStatusEnum;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Models\WhatsAppMessageCredit;
use App\Services\Meta\MetaTemplateService;
use App\Services\WhatsAppVariableResolver;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendBroadcastJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    private const CHUNK_SIZE = 50;

    private const LOAD_CHUNK_SIZE = 500;

    /**
     * @param  int  $broadcastId  The broadcast to send
     * @param  int  $gymId  Gym for credit/service resolution
     * @param  bool  $skipCreditDeduction  When true, credits were deducted upfront by BroadcastOrchestrator
     */
    public function __construct(
        public int $broadcastId,
        public int $gymId,
        public bool $skipCreditDeduction = false,
    ) {}

    /**
     * The unique ID of the job.
     *
     * Prevents duplicate SendBroadcastJob instances for the same broadcast
     * from being queued simultaneously.
     */
    public function uniqueId(): string
    {
        return "send-broadcast:{$this->broadcastId}";
    }

    public function handle(): void
    {
        /** @var WhatsAppBroadcast|null $broadcast */
        $broadcast = WhatsAppBroadcast::query()->find($this->broadcastId);

        if (! $broadcast) {
            Log::warning('Broadcast not found for batch orchestration', [
                'broadcast_id' => $this->broadcastId,
            ]);

            return;
        }

        if ($broadcast->status?->isFinished()) {
            Log::info('Broadcast already processed, skipping batch dispatch', [
                'broadcast_id' => $this->broadcastId,
                'status' => $broadcast->status,
            ]);

            return;
        }

        $broadcast->markAsSending();

        $template = $broadcast->template;

        if (blank($template)) {
            Log::warning('Broadcast template not found for batch orchestration', [
                'broadcast_id' => $this->broadcastId,
            ]);

            $broadcast->markAsFailed();

            return;
        }

        $resolver = resolve(WhatsAppVariableResolver::class);
        $customVariables = $broadcast->message_variables ?? [];
        $templateData = [
            'provider_template_id' => $template->name,
            'language' => MetaTemplateService::defaultTemplateLanguage(),
            'components' => [],
            'media_urls' => [],
        ];

        $allJobs = [];
        $totalProcessed = 0;

        WhatsAppBroadcastRecipient::query()->where('whatsapp_broadcast_id', $this->broadcastId)
            ->where('status', WhatsAppBroadcastRecipientStatusEnum::PENDING->value)
            ->with([
                'member:id,gym_id,trainer_id,gym_membership_plan_id,name,phone,email,dob,membership_start,membership_end,is_archived,allow_whatsapp_marketing',
                'member.gym:id,name',
                'member.membershipPlan:id,name,price,duration',
                'member.trainer:id,name,phone,specialization',
                'member.latestPayment' => function (Relation $query): void {
                    // Qualify columns explicitly: the latestOfMany derived-table
                    // join exposes a "member_id" column, so an unqualified select
                    // would raise SQLSTATE 23000/1052 (ambiguous column).
                    // "formatted_amount" is a computed accessor, not a column,
                    // so it is omitted in favour of the raw "currency" column.
                    $query->select($query->qualifyColumns([
                        'id',
                        'member_id',
                        'amount',
                        'currency',
                        'payment_date',
                        'payment_method',
                        'invoice_number',
                    ]));
                },
            ])
            ->chunkById(self::LOAD_CHUNK_SIZE, function ($recipients) use (
                $broadcast,
                $resolver,
                $customVariables,
                $templateData,
                &$allJobs,
                &$totalProcessed,
            ): void {
                $chunkJobs = [];

                foreach ($recipients as $recipient) {
                    $member = $recipient->member;

                    if ($member === null) {
                        continue;
                    }

                    if (! $member->canReceiveWhatsappNotifications()) {
                        continue;
                    }

                    $context = ['member' => $member, 'custom' => $customVariables];
                    $variables = $resolver->resolve($broadcast->template, $context);

                    $chunkJobs[] = [
                        'id' => $recipient->id,
                        'phone_number' => $recipient->phone_number,
                        'member_name' => $recipient->member_name,
                        'resolved_variables' => $variables,
                    ];
                }

                $totalProcessed += count($chunkJobs);

                $groups = array_chunk($chunkJobs, self::CHUNK_SIZE);

                foreach ($groups as $group) {
                    $allJobs[] = new SendMessageChunkJob(
                        broadcastId: $this->broadcastId,
                        recipientChunk: $group,
                        templateData: $templateData,
                        gymId: $this->gymId,
                        skipCreditDeduction: $this->skipCreditDeduction,
                    );
                }
            });

        if ($totalProcessed === 0) {
            Log::warning('No pending recipients for broadcast', [
                'broadcast_id' => $this->broadcastId,
            ]);

            $broadcast->markAsCompleted();

            return;
        }

        $broadcastId = $this->broadcastId;
        $gymId = $this->gymId;
        $skipCreditDeduction = $this->skipCreditDeduction;

        $batch = Bus::batch($allJobs)
            ->then(function (Batch $batch) use ($broadcastId): void {
                DB::table('whatsapp_broadcasts')
                    ->where('id', $broadcastId)
                    ->whereIn('status', [WhatsAppBroadcastStatusEnum::SENDING->value])
                    ->update([
                        'status' => WhatsAppBroadcastStatusEnum::COMPLETED->value,
                        'completed_at' => now(),
                    ]);

                Log::info('Broadcast batch completed successfully', [
                    'broadcast_id' => $broadcastId,
                    'total_jobs' => $batch->totalJobs,
                    'pending_jobs' => $batch->pendingJobs,
                    'failed_jobs' => $batch->failedJobs,
                ]);
            })
            ->catch(function (Batch $batch) use ($broadcastId): void {
                DB::table('whatsapp_broadcasts')
                    ->where('id', $broadcastId)
                    ->where('status', '!=', WhatsAppBroadcastStatusEnum::COMPLETED->value)
                    ->update([
                        'status' => WhatsAppBroadcastStatusEnum::FAILED->value,
                        'completed_at' => now(),
                    ]);

                Log::error('Broadcast batch failed', [
                    'broadcast_id' => $broadcastId,
                    'failed_jobs' => $batch->failedJobs,
                ]);
            })
            ->finally(function (Batch $batch) use ($gymId, $broadcastId, $skipCreditDeduction): void {
                // Release the per-gym rate limiter lock
                Cache::lock("broadcast-rate-{$gymId}", 10)->forceRelease();

                // If credits were deducted upfront, refund any that failed
                self::refundFailedUpfrontCredits($gymId, $broadcastId, $skipCreditDeduction);
            })
            ->onQueue(config()->string('queue.broadcast_queue', 'broadcast'))
            ->dispatch();

        $broadcast->update(['batch_id' => $batch->id]);

        Log::info('Broadcast batch dispatched', [
            'broadcast_id' => $this->broadcastId,
            'batch_id' => $batch->id,
            'chunks' => count($allJobs),
            'total_recipients' => $totalProcessed,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendBroadcastJob permanently failed', [
            'broadcast_id' => $this->broadcastId,
            'gym_id' => $this->gymId,
            'error' => $exception->getMessage(),
        ]);

        /** @var WhatsAppBroadcast|null $broadcast */
        $broadcast = WhatsAppBroadcast::query()->where('id', $this->broadcastId)->first();

        if (! $broadcast) {
            return;
        }

        $broadcast->update(['status' => WhatsAppBroadcastStatusEnum::FAILED]);

        // If credits were deducted upfront but the batch was never dispatched
        // (e.g. queue dispatch failure), the batch's finally closure will never
        // run, so refund the full upfront deduction here.
        if ($this->skipCreditDeduction && $broadcast->batch_id === null) {
            self::refundUpfrontCredits($this->gymId, $this->broadcastId, $broadcast->target_count);
        }
    }

    /**
     * Refund upfront credits for a broadcast whose batch finished in a failed state.
     *
     * Called from the batch's finally closure when credits were deducted upfront.
     * Only refunds when credits were deducted upfront, the batch-level catch
     * handler marked the broadcast FAILED, and at least one chunk failed.
     */
    public static function refundFailedUpfrontCredits(int $gymId, int $broadcastId, bool $skipCreditDeduction = false): void
    {
        if (! $skipCreditDeduction) {
            return;
        }

        $broadcastRecord = DB::table('whatsapp_broadcasts')
            ->where('id', $broadcastId)
            ->first(['status', 'failed_count', 'credits_used']);

        if (! $broadcastRecord || $broadcastRecord->status !== WhatsAppBroadcastStatusEnum::FAILED->value) {
            return;
        }

        $failedCount = $broadcastRecord->failed_count;

        if (is_numeric($failedCount) && $failedCount > 0) {
            self::refundUpfrontCredits($gymId, $broadcastId, (int) $failedCount);
        }
    }

    /**
     * Refund credits that were deducted upfront for a failed broadcast.
     *
     * Restores the paid balance (remaining/used credits) and, when the failed
     * deduction consumed welcome credits, also restores the welcome portion so
     * the welcome credit ledger stays consistent with the refunded balance.
     */
    private static function refundUpfrontCredits(int $gymId, int $broadcastId, int $refundCount): void
    {
        if ($refundCount <= 0) {
            return;
        }

        $broadcastRecord = DB::table('whatsapp_broadcasts')
            ->where('id', $broadcastId)
            ->first(['credits_used']);

        if (! $broadcastRecord) {
            return;
        }

        // Never refund more than the broadcast still has deducted, which also
        // keeps the refund idempotent if both the finally closure and failed()
        // ever run for the same broadcast.
        $creditsUsed = $broadcastRecord->credits_used;
        $refundCount = min($refundCount, is_numeric($creditsUsed) ? (int) $creditsUsed : 0);

        if ($refundCount <= 0) {
            return;
        }

        // Atomically refund paid credits: increment remaining, decrement used
        WhatsAppMessageCredit::query()->where('gym_id', $gymId)
            ->update([
                'remaining_credits' => DB::raw('remaining_credits + ' . $refundCount),
                'used_credits' => DB::raw('GREATEST(used_credits - ' . $refundCount . ', 0)'),
                'last_used_at' => now(),
            ]);

        // Restore the welcome-credit portion of the refund so the welcome
        // credit ledger mirrors the refunded paid balance.
        $credit = WhatsAppMessageCredit::query()->where('gym_id', $gymId)->first();

        if ($credit && $credit->welcome_credits_used > 0) {
            $welcomeToRestore = min($refundCount, $credit->welcome_credits_used);

            $credit->decrement('welcome_credits_used', $welcomeToRestore);
        }

        // Also update the broadcast's credits_used to reflect the refund
        DB::table('whatsapp_broadcasts')
            ->where('id', $broadcastId)
            ->update([
                'credits_used' => DB::raw('GREATEST(credits_used - ' . $refundCount . ', 0)'),
            ]);

        Log::info('Broadcast failed credits refunded', [
            'broadcast_id' => $broadcastId,
            'gym_id' => $gymId,
            'refunded_count' => $refundCount,
        ]);
    }
}
