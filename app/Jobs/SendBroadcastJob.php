<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\WhatsAppBroadcastRecipientStatusEnum;
use App\Enums\WhatsAppBroadcastStatusEnum;
use App\Helpers\WhatsAppSanitizer;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendBroadcastJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public ?string $dispatchedBatchId = null;

    private const CHUNK_SIZE = 50;

    private const LOAD_CHUNK_SIZE = 500;

    public const SKIPPED_MEMBER_NOT_FOUND = 'Member record not found';

    public const SKIPPED_MEMBER_OPTED_OUT = 'Member opted out or was archived before send';

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

        if (! $broadcast || $broadcast->gym_id !== $this->gymId) {
            Log::warning('Broadcast not found or gym mismatch for batch orchestration', [
                'broadcast_id' => $this->broadcastId,
                'gym_id' => $this->gymId,
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

            if ($this->skipCreditDeduction) {
                self::refundFailedUpfrontCredits($this->gymId, $this->broadcastId, true);
            }

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
                        DB::transaction(function () use ($recipient): void {
                            $recipient->update([
                                'status' => WhatsAppBroadcastRecipientStatusEnum::FAILED->value,
                                'failure_reason' => self::SKIPPED_MEMBER_NOT_FOUND,
                            ]);
                            DB::table('whatsapp_broadcasts')
                                ->where('id', $this->broadcastId)
                                ->increment('failed_count');
                        });

                        continue;
                    }

                    if (! $member->canReceiveWhatsappNotifications()) {
                        DB::transaction(function () use ($recipient): void {
                            $recipient->update([
                                'status' => WhatsAppBroadcastRecipientStatusEnum::FAILED->value,
                                'failure_reason' => self::SKIPPED_MEMBER_OPTED_OUT,
                            ]);
                            DB::table('whatsapp_broadcasts')
                                ->where('id', $this->broadcastId)
                                ->increment('failed_count');
                        });

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

            $targetCount = $broadcast->target_count;
            $failedCount = $broadcast->failed_count;
            $sentCount = $broadcast->sent_count;
            $status = WhatsAppBroadcastStatusEnum::fromCounts($sentCount, $failedCount, $targetCount);

            $broadcast->update([
                'status' => $status,
                'completed_at' => $status === WhatsAppBroadcastStatusEnum::FAILED ? null : now(),
            ]);

            if ($this->skipCreditDeduction) {
                self::refundFailedUpfrontCredits($this->gymId, $this->broadcastId, true);
            }

            return;
        }

        $broadcastId = $this->broadcastId;
        $gymId = $this->gymId;
        $skipCreditDeduction = $this->skipCreditDeduction;

        $batch = Bus::batch($allJobs)
            ->allowFailures()
            ->finally(function (Batch $batch) use ($gymId, $broadcastId, $skipCreditDeduction): void {
                try {
                    DB::transaction(function () use ($gymId, $broadcastId, $skipCreditDeduction, $batch): void {
                        // Update broadcast status when batch finishes strictly from per-recipient counts
                        $broadcast = DB::table('whatsapp_broadcasts')
                            ->where('id', $broadcastId)
                            ->where('gym_id', $gymId)
                            ->lockForUpdate()
                            ->first(['sent_count', 'failed_count', 'target_count', 'status']);

                        $status = WhatsAppBroadcastStatusEnum::COMPLETED->value;
                        if ($broadcast) {
                            $sent = is_numeric($broadcast->sent_count) ? (int) $broadcast->sent_count : 0;
                            $failed = is_numeric($broadcast->failed_count) ? (int) $broadcast->failed_count : 0;
                            $target = is_numeric($broadcast->target_count) ? (int) $broadcast->target_count : 0;

                            $status = WhatsAppBroadcastStatusEnum::fromCounts($sent, $failed, $target)->value;
                        }

                        DB::table('whatsapp_broadcasts')
                            ->where('id', $broadcastId)
                            ->where('gym_id', $gymId)
                            ->whereIn('status', [WhatsAppBroadcastStatusEnum::SENDING->value])
                            ->update([
                                'status' => $status,
                                'completed_at' => $status === WhatsAppBroadcastStatusEnum::FAILED->value ? null : now(),
                            ]);

                        Log::info('Broadcast batch completed', [
                            'broadcast_id' => $broadcastId,
                            'status' => $status,
                            'total_jobs' => $batch->totalJobs,
                            'pending_jobs' => $batch->pendingJobs,
                            'failed_jobs' => $batch->failedJobs,
                        ]);

                        // If credits were deducted upfront, refund any that failed
                        if ($skipCreditDeduction) {
                            self::refundUpfrontCreditsInTransaction($gymId, $broadcastId);
                        }
                    });
                } catch (Throwable $e) {
                    Log::error('Error during broadcast batch finalization', [
                        'broadcast_id' => $broadcastId,
                        'gym_id' => $gymId,
                        'error' => WhatsAppSanitizer::sanitizeError($e->getMessage()),
                    ]);
                }
            })
            ->onQueue(config()->string('queue.broadcast_queue', 'broadcast'))
            ->dispatch();

        $this->dispatchedBatchId = $batch->id;

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
            'error' => WhatsAppSanitizer::sanitizeError($exception->getMessage()),
        ]);

        /** @var WhatsAppBroadcast|null $broadcast */
        $broadcast = WhatsAppBroadcast::query()
            ->where('id', $this->broadcastId)
            ->where('gym_id', $this->gymId)
            ->first();

        if (! $broadcast) {
            return;
        }

        if ($this->dispatchedBatchId !== null && $broadcast->batch_id === null) {
            $broadcast->update(['batch_id' => $this->dispatchedBatchId]);
            $broadcast->batch_id = $this->dispatchedBatchId;
        }

        if ($broadcast->status === WhatsAppBroadcastStatusEnum::SENDING) {
            $broadcast->update([
                'status' => WhatsAppBroadcastStatusEnum::FAILED,
                'completed_at' => null,
            ]);
        }

        if ($this->skipCreditDeduction && $broadcast->batch_id === null && $this->dispatchedBatchId === null) {
            self::refundFailedUpfrontCredits($this->gymId, $this->broadcastId, true);
        }
    }

    /**
     * Refund upfront credits for a broadcast whose batch has completed.
     *
     * Called from the batch's finally closure when credits were deducted upfront.
     * Only refunds when credits were deducted upfront, the broadcast is in a terminal
     * state (FAILED, COMPLETED, COMPLETED_WITH_ERRORS, or CANCELLED), and failed_count is greater than 0.
     */
    public static function refundFailedUpfrontCredits(int $gymId, int $broadcastId, bool $creditsWereDeductedUpfront = false): void
    {
        if (! $creditsWereDeductedUpfront) {
            return;
        }

        DB::transaction(function () use ($gymId, $broadcastId): void {
            self::refundUpfrontCreditsInTransaction($gymId, $broadcastId);
        });
    }

    /**
     * Refund credits that were deducted upfront for a failed broadcast within an active DB transaction.
     */
    private static function refundUpfrontCreditsInTransaction(int $gymId, int $broadcastId): void
    {
        $broadcastRecord = DB::table('whatsapp_broadcasts')
            ->where('id', $broadcastId)
            ->where('gym_id', $gymId)
            ->lockForUpdate()
            ->first(['status', 'failed_count', 'credits_used', 'sent_count', 'refunded_credits']);

        if (! $broadcastRecord || ! in_array($broadcastRecord->status, [WhatsAppBroadcastStatusEnum::FAILED->value, WhatsAppBroadcastStatusEnum::COMPLETED->value, WhatsAppBroadcastStatusEnum::COMPLETED_WITH_ERRORS->value, WhatsAppBroadcastStatusEnum::CANCELLED->value], true)) {
            return;
        }

        $creditsUsed = is_numeric($broadcastRecord->credits_used) ? (int) $broadcastRecord->credits_used : 0;
        $sentCount = is_numeric($broadcastRecord->sent_count) ? (int) $broadcastRecord->sent_count : 0;
        $alreadyRefunded = is_numeric($broadcastRecord->refunded_credits) ? (int) $broadcastRecord->refunded_credits : 0;

        $totalRefundableNeeded = max(0, $creditsUsed - $sentCount);
        $refundCount = max(0, $totalRefundableNeeded - $alreadyRefunded);

        if ($refundCount <= 0) {
            return;
        }

        // Atomically refund paid and welcome credits in a single locked query
        $credit = WhatsAppMessageCredit::query()
            ->where('gym_id', $gymId)
            ->lockForUpdate()
            ->first();

        if ($credit) {
            $welcomeCreditsUsed = ($credit->welcome_credits_used ?? 0);
            $welcomeToRestore = 0;
            if ($welcomeCreditsUsed > 0) {
                $welcomeToRestore = min($refundCount, $welcomeCreditsUsed);
            }
            $paidRefund = $refundCount - $welcomeToRestore;
            $usedCredits = max(0, ($credit->used_credits ?? 0) - $paidRefund);

            WhatsAppMessageCredit::query()->where('gym_id', $gymId)
                ->increment('remaining_credits', $refundCount, [
                    'used_credits' => $usedCredits,
                    'welcome_credits_used' => max(0, $welcomeCreditsUsed - $welcomeToRestore),
                    'last_used_at' => now(),
                ]);
        }

        // Update the broadcast's refunded_credits and refunded_at without mutating credits_used
        DB::table('whatsapp_broadcasts')
            ->where('id', $broadcastId)
            ->where('gym_id', $gymId)
            ->update([
                'refunded_credits' => DB::raw('refunded_credits + ' . $refundCount),
                'refunded_at' => now(),
            ]);

        Log::info('Broadcast failed credits refunded', [
            'broadcast_id' => $broadcastId,
            'gym_id' => $gymId,
            'refunded_count' => $refundCount,
            'total_refunded' => $alreadyRefunded + $refundCount,
        ]);
    }
}
