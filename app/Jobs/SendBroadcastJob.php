<?php

namespace App\Jobs;

use App\Enums\WhatsAppBroadcastRecipientStatusEnum;
use App\Enums\WhatsAppBroadcastStatusEnum;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Models\WhatsAppMessageCredit;
use App\Services\WhatsAppVariableResolver;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        if (in_array($broadcast->status, [WhatsAppBroadcastStatusEnum::COMPLETED, WhatsAppBroadcastStatusEnum::CANCELLED])) {
            Log::info('Broadcast already processed, skipping batch dispatch', [
                'broadcast_id' => $this->broadcastId,
                'status' => $broadcast->status,
            ]);

            return;
        }

        $broadcast->markAsSending();

        $resolver = resolve(WhatsAppVariableResolver::class);
        $customVariables = $broadcast->message_variables ?? [];
        $template = $broadcast->template;
        $templateData = [
            'provider_template_id' => $template->name,
            'language' => 'en',
            'components' => [],
            'media_urls' => [],
        ];

        $allJobs = [];
        $totalProcessed = 0;

        WhatsAppBroadcastRecipient::query()->where('whatsapp_broadcast_id', $this->broadcastId)
            ->where('status', WhatsAppBroadcastRecipientStatusEnum::PENDING->value)
            ->with([
                'member:id,gym_id,trainer_id,gym_membership_plan_id,name,phone,email,dob,membership_start,membership_end',
                'member.gym:id,name',
                'member.membershipPlan:id,name,price,duration',
                'member.trainer:id,name,phone,specialization',
                'member.latestPayment:id,member_id,amount,formatted_amount,payment_date,payment_method,invoice_number',
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
                    if ($recipient->member === null) {
                        continue;
                    }

                    $context = ['member' => $recipient->member, 'custom' => $customVariables];
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
        $skipDeduction = $this->skipCreditDeduction;

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
            ->finally(function (Batch $batch) use ($gymId, $broadcastId, $skipDeduction): void {
                // Release the per-gym rate limiter lock
                Cache::lock("broadcast-rate-{$gymId}", 10)->forceRelease();

                // If credits were deducted upfront, refund any that failed
                if ($skipDeduction) {
                    $broadcastRecord = DB::table('whatsapp_broadcasts')
                        ->where('id', $broadcastId)
                        ->first(['sent_count', 'failed_count', 'target_count', 'status']);

                    if (! $broadcastRecord || $broadcastRecord->status !== WhatsAppBroadcastStatusEnum::FAILED->value) {
                        return;
                    }

                    if (($broadcastRecord->failed_count > 0)) {
                        $refundCount = (int) $broadcastRecord->failed_count;

                        // Atomically refund credits: increment remaining, decrement used
                        WhatsAppMessageCredit::query()->where('gym_id', $gymId)
                            ->update([
                                'remaining_credits' => DB::raw('remaining_credits + ' . $refundCount),
                                'used_credits' => DB::raw('GREATEST(used_credits - ' . $refundCount . ', 0)'),
                                'last_used_at' => now(),
                            ]);

                        // Also update the broadcast's credits_used to reflect actual sends
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
            })
            ->onQueue(config('queue.broadcast_queue', 'broadcast'))
            ->dispatch();

        $broadcast->update(['batch_id' => $batch->id]);

        Log::info('Broadcast batch dispatched', [
            'broadcast_id' => $this->broadcastId,
            'batch_id' => $batch->id,
            'chunks' => count($allJobs),
            'total_recipients' => $totalProcessed,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendBroadcastJob permanently failed', [
            'broadcast_id' => $this->broadcastId,
            'gym_id' => $this->gymId,
            'error' => $exception->getMessage(),
        ]);

        WhatsAppBroadcast::query()->where('id', $this->broadcastId)
            ->update(['status' => WhatsAppBroadcastStatusEnum::FAILED]);
    }
}
