<?php

namespace App\Services\WhatsApp;

use App\Interfaces\MessagingInterface;
use App\Models\GymMember;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use Illuminate\Support\Facades\Log;

/**
 * Shared WhatsApp Broadcast Service.
 *
 * Handles the heavy lifting of chunking recipients and tracking progress.
 * Only relies on the MessagingInterface to actually send the individual messages.
 */
class WhatsAppBroadcastService
{
    public function __construct(
        protected MessagingInterface $messagingService,
        protected int $gymId
    ) {}

    /**
     * Process a broadcast by chunking recipients and sending template messages.
     */
    public function processBroadcast(int $broadcastId): array
    {
        $results = [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
        ];

        try {
            $broadcast = WhatsAppBroadcast::with('template')
                ->where('gym_id', $this->gymId)
                ->findOrFail($broadcastId);

            $broadcast->markAsSending();

            $query = $broadcast->recipients()->where('status', 'pending');
            $results['total'] = $query->count();

            $query->chunkById(100, function ($recipients) use ($broadcast, &$results) {
                foreach ($recipients as $recipient) {
                    $this->processRecipient($broadcast, $recipient, $results);
                }
            });

            $broadcast->markAsCompleted();

            Log::info('Broadcast completed', [
                'broadcast_id' => $broadcastId,
                'results' => $results,
            ]);

            return $results;
        } catch (\Exception $e) {
            Log::error('Broadcast error', [
                'broadcast_id' => $broadcastId,
                'error' => $e->getMessage(),
            ]);

            return $results;
        }
    }

    /**
     * Process a single broadcast recipient.
     */
    private function processRecipient(
        WhatsAppBroadcast $broadcast,
        WhatsAppBroadcastRecipient $recipient,
        array &$results
    ): void {
        try {
            $variables = $this->prepareVariables($broadcast, $recipient);

            $result = $this->messagingService->sendTemplateMessage(
                $recipient->phone_number,
                $broadcast->template->name,
                $variables
            );

            if ($result['success']) {
                $recipient->markAsSent($result['message_id']);
                $broadcast->incrementSentCount();
                $results['sent']++;
            } else {
                $recipient->markAsFailed($result['error']);
                $broadcast->incrementFailedCount();
                $results['failed']++;
            }
        } catch (\Exception $e) {
            $recipient->markAsFailed($e->getMessage());
            $broadcast->incrementFailedCount();
            $results['failed']++;
        }
    }

    /**
     * Resolve template variables for a recipient.
     */
    private function prepareVariables(WhatsAppBroadcast $broadcast, WhatsAppBroadcastRecipient $recipient): array
    {
        $member = GymMember::with(['gym', 'membershipPlan', 'trainer', 'latestPayment'])
            ->find($recipient->member_id);

        return app(\App\Services\WhatsAppVariableResolver::class)->resolve(
            $broadcast->template,
            [
                'member' => $member,
                'custom' => $broadcast->message_variables ?? [],
            ]
        );
    }
}
