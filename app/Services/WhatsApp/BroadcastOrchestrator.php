<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendBroadcastJob;
use App\Models\GymMember;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppTemplate;
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
     * @throws \Exception On any validation failure
     */
    public function createAndDispatch(
        array $data,
        int $gymId,
        array $selectedMemberIds = [],
    ): WhatsAppBroadcast {
        $lock = Cache::lock("broadcast-rate-{$gymId}", 10);

        if (! $lock->get()) {
            throw new \Exception('Unable to send broadcast at this time. Please try again.');
        }

        try {
            $templateId = $data['template_id'] ?? null;
            $variables = $data['variables'] ?? [];
            $recipientType = $data['recipient_type'] ?? 'all';
            $scheduleType = $data['schedule_type'] ?? 'now';
            $scheduledAt = $data['scheduled_at'] ?? null;
            $filters = $data['filters'] ?? [];

            /** @var WhatsAppTemplate|null $template */
            $template = WhatsAppTemplate::find($templateId);

            if (! $template) {
                throw new \Exception('Please select a template');
            }

            $targetCount = $recipientType === 'all'
                ? GymMember::where('gym_id', $gymId)->whereNotNull('phone')->where('phone', '!=', '')->count()
                : count($selectedMemberIds);

            if ($targetCount === 0) {
                throw new \Exception('No recipients found. Please select members or change recipient type.');
            }

            if ($recipientType !== 'all' && empty($selectedMemberIds)) {
                throw new \Exception('Please select at least one member from the filtered results.');
            }

            // Rate limiting check (atomic)
            if (! $this->rateLimiter->canSend($gymId)) {
                $quota = $this->rateLimiter->getQuota($gymId);
                $cooldown = $this->rateLimiter->getCooldown($gymId);

                if ($quota['remaining'] <= 0) {
                    throw new \Exception('Daily broadcast limit reached. You can send a maximum of ' . $quota['limit'] . ' broadcasts per day. Try again tomorrow.');
                }

                if ($cooldown !== null) {
                    throw new \Exception("Please wait {$cooldown} more minute(s) before sending another broadcast. Minimum gap is 1 hour between broadcasts.");
                }

                throw new \Exception('Unable to send broadcast at this time. Please try again.');
            }

            $creditBalance = WhatsAppMessageCredit::where('gym_id', $gymId)->first();

            if (! $creditBalance || ! $creditBalance->canSendMessage($targetCount)) {
                throw new \Exception('Insufficient credits. You need ' . number_format($targetCount) . ' credits.');
            }

            DB::beginTransaction();

            try {
                $broadcast = WhatsAppBroadcast::create([
                    'gym_id' => $gymId,
                    'whatsapp_template_id' => $templateId,
                    'title' => 'Broadcast: ' . $template->display_name,
                    'target_filters' => $recipientType === 'filtered'
                        ? $filters
                        : ['type' => 'all'],
                    'target_count' => $targetCount,
                    'status' => $scheduleType === 'now' ? 'sending' : 'scheduled',
                    'scheduled_at' => $scheduleType === 'later' ? $scheduledAt : null,
                    'message_variables' => $variables,
                ]);

                $memberQuery = GymMember::where('gym_id', $gymId);

                if ($recipientType !== 'all') {
                    $memberQuery->whereIn('id', $selectedMemberIds);
                }

                $now = now();

                $memberQuery->chunk(500, function ($members) use ($broadcast, $now): void {
                    $recipients = [];

                    foreach ($members as $member) {
                        if (empty($member->phone)) {
                            continue;
                        }

                        $recipients[] = [
                            'whatsapp_broadcast_id' => $broadcast->id,
                            'member_id' => $member->id,
                            'phone_number' => $member->phone,
                            'member_name' => $member->name ?? 'Member',
                            'status' => 'pending',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if (! empty($recipients)) {
                        WhatsAppBroadcastRecipient::insert($recipients);
                    }
                });

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();

                throw $e;
            }

            // Dispatch job for immediate broadcasts
            if ($scheduleType === 'now') {
                SendBroadcastJob::dispatch($broadcast->id, $gymId);
            }

            return $broadcast;
        } finally {
            $lock->release();
        }
    }
}
