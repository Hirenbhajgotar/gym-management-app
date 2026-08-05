<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Enums\WhatsAppBroadcastRecipientStatusEnum;
use App\Jobs\SendBroadcastJob;
use App\Models\GymMember;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppTemplate;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BroadcastOrchestrator
{
    public function __construct(
        private BroadcastRateLimiter $rateLimiter
    ) {}

    /**
     * Create a broadcast and dispatch it (or schedule it).
     *
     * @param  array<string, mixed>  $data  Template, variables, recipient/schedule config
     * @param  int[]  $selectedMemberIds  Pre-filtered member IDs (for 'filtered' recipient type)
     *
     * @throws Exception On any validation failure
     */
    public function createAndDispatch(
        array $data,
        int $gymId,
        array $selectedMemberIds = [],
    ): WhatsAppBroadcast {
        $lock = Cache::lock("broadcast-rate-{$gymId}", 10);

        throw_unless($lock->get(), Exception::class, 'Unable to send broadcast at this time. Please try again.');

        try {
            $templateId = $data['template_id'] ?? null;
            $variables = $data['variables'] ?? [];
            $recipientType = $data['recipient_type'] ?? 'all';
            $scheduleType = $data['schedule_type'] ?? 'now';
            $scheduledAt = $data['scheduled_at'] ?? null;
            $filters = $data['filters'] ?? [];
            $creditsDeducted = false;

            /** @var WhatsAppTemplate|null $template */
            $template = WhatsAppTemplate::query()->find($templateId);

            throw_unless($template, Exception::class, 'Please select a template');

            $targetCount = $recipientType === 'all'
                ? GymMember::query()->where('gym_id', $gymId)->whereNotNull('phone')->where('phone', '!=', '')->count()
                : count($selectedMemberIds);

            throw_if($targetCount === 0, Exception::class, 'No recipients found. Please select members or change recipient type.');

            throw_if($recipientType !== 'all' && blank($selectedMemberIds), Exception::class, 'Please select at least one member from the filtered results.');

            if (! $this->rateLimiter->canSend($gymId)) {
                $quota = $this->rateLimiter->getQuota($gymId);
                $cooldown = $this->rateLimiter->getCooldown($gymId);

                if ($quota['remaining'] <= 0) {
                    throw new Exception('Daily broadcast limit reached. You can send a maximum of ' . $quota['limit'] . ' broadcasts per day. Try again tomorrow.');
                }

                throw_if($cooldown !== null, Exception::class, "Please wait {$cooldown} more minute(s) before sending another broadcast. Minimum gap is 1 hour between broadcasts.");

                throw new Exception('Unable to send broadcast at this time. Please try again.');
            }

            $isSelfManagedBillingMode = WhatsAppAccount::query()
                ->where('gym_id', $gymId)
                ->selfManageBillingMode()
                ->exists();

            return DB::transaction(function () use (
                $isSelfManagedBillingMode,
                $gymId,
                $targetCount,
                &$creditsDeducted,
                $templateId,
                $template,
                $variables,
                $recipientType,
                $filters,
                $scheduleType,
                $scheduledAt,
                $selectedMemberIds
            ) {
                if (! $isSelfManagedBillingMode) {
                    // === Upfront credit deduction ===
                    // Deduct all required credits atomically within a lock-protected
                    // transaction to prevent race conditions across concurrent broadcasts.
                    /** @var WhatsAppMessageCredit|null $creditRecord */
                    $creditRecord = WhatsAppMessageCredit::query()->where('gym_id', $gymId)
                        ->lockForUpdate()
                        ->first();

                    if (! $creditRecord || ! $creditRecord->canSendMessage($targetCount)) {
                        throw new Exception('Insufficient credits. You need ' . number_format($targetCount) . ' credits.');
                    }

                    // Handle welcome credits first, then bulk deduction
                    $welcomeAvailable = $creditRecord->getWelcomeCreditsRemaining();

                    $welcomeToUse = 0;
                    if ($welcomeAvailable > 0) {
                        $welcomeToUse = min($welcomeAvailable, $targetCount);
                        $creditRecord->increment('welcome_credits_used', $welcomeToUse);
                    }
                    $paidToDeduct = max(0, $targetCount - $welcomeToUse);
                    DB::table('whatsapp_message_credits')
                        ->where('gym_id', $gymId)
                        ->update([
                            'used_credits' => DB::raw('used_credits + ' . $paidToDeduct),
                            'remaining_credits' => DB::raw('remaining_credits - ' . $targetCount),
                            'last_used_at' => now(),
                        ]);

                    $creditsDeducted = true;
                }

                $broadcast = WhatsAppBroadcast::query()->create([
                    'gym_id' => $gymId,
                    'whatsapp_template_id' => $templateId,
                    'title' => 'Broadcast: ' . $template->display_name,
                    'target_filters' => $recipientType === 'filtered'
                        ? $filters
                        : ['type' => 'all'],
                    'target_count' => $targetCount,
                    'credits_used' => $targetCount,
                    'status' => $scheduleType === 'now' ? 'sending' : 'scheduled',
                    'scheduled_at' => $scheduleType === 'later' ? $scheduledAt : null,
                    'message_variables' => $variables,
                ]);

                $memberQuery = GymMember::query()->where('gym_id', $gymId)->whereNotNull('phone')->where('phone', '!=', '');

                if ($recipientType !== 'all') {
                    $memberQuery->whereIn('id', $selectedMemberIds);
                }

                $now = now();

                $memberQuery->chunk(500, function ($members) use ($broadcast, $now): void {
                    $recipients = [];

                    foreach ($members as $member) {
                        if (blank($member->phone)) {
                            continue;
                        }

                        $recipients[] = [
                            'whatsapp_broadcast_id' => $broadcast->id,
                            'member_id' => $member->id,
                            'phone_number' => $member->phone,
                            'member_name' => $member->name ?? 'Member',
                            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING->value,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if (filled($recipients)) {
                        WhatsAppBroadcastRecipient::query()->insert($recipients);
                    }
                });

                // Dispatch job for immediate broadcasts
                // after_commit is enabled in config/queue.php
                if ($scheduleType === 'now') {
                    dispatch(new SendBroadcastJob($broadcast->id, $gymId, skipCreditDeduction: $creditsDeducted));
                }

                return $broadcast;
            }, 5);
        } finally {
            $lock->release();
        }
    }
}
