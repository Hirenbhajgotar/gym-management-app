<?php

namespace App\Services\Meta;

use App\Interfaces\CreditManagementInterface;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppUsageLog;
use Exception;

/**
 * Meta Credit Service.
 *
 * Implements CreditManagementInterface for credit balance
 * and usage tracking via Meta WhatsApp API.
 */
class MetaCreditService implements CreditManagementInterface
{
    public function __construct(
        protected MetaContext $context
    ) {}

    /**
     * Get credit balance for the gym.
     */
    public function getCreditBalance(): array
    {
        $credit = WhatsAppMessageCredit::where('gym_id', $this->context->gymId)->first();

        if (! $credit) {
            return [
                'total_credits' => 0,
                'used_credits' => 0,
                'remaining_credits' => 0,
                'welcome_credits_remaining' => 0,
                'can_send' => false,
            ];
        }

        return [
            'total_credits' => $credit->total_credits,
            'used_credits' => $credit->used_credits,
            'remaining_credits' => $credit->remaining_credits,
            'welcome_credits_remaining' => $credit->getWelcomeCreditsRemaining(),
            'can_send' => $credit->canSendMessage(),
            'usage_percentage' => $credit->getUsagePercentage(),
        ];
    }

    /**
     * Get monthly usage statistics.
     */
    public function getMonthlyUsage(int $year, int $month): array
    {
        $logs = WhatsAppUsageLog::where('gym_id', $this->context->gymId)
            ->whereYear('sent_at', $year)
            ->whereMonth('sent_at', $month)
            ->get();

        return [
            'total_messages' => $logs->count(),
            'delivered' => $logs->where('status', 'delivered')->count(),
            'failed' => $logs->where('status', 'failed')->count(),
            'total_cost' => $logs->sum('twilio_cost'),
        ];
    }

    /**
     * Check and deduct credits before sending.
     *
     * @throws Exception
     */
    public function checkAndDeduct(int $count = 1): void
    {
        $credit = WhatsAppMessageCredit::where('gym_id', $this->context->gymId)->first();

        throw_if(! $credit || ! $credit->canSendMessage($count), Exception::class, 'Insufficient message credits. Please purchase more credits.');

        if ($credit->hasWelcomeCredits() && $credit->getWelcomeCreditsRemaining() >= $count) {
            $credit->useWelcomeCredit();
        } else {
            $credit->deductCredits($count);
        }
    }

    /**
     * Log message usage.
     */
    public function logUsage(string $recipient, ?int $templateId, ?string $messageId): void
    {
        WhatsAppUsageLog::create([
            'gym_id' => $this->context->gymId,
            'whatsapp_template_id' => $templateId,
            'twilio_message_sid' => $messageId,
            'recipient' => $recipient,
            'credits_used' => 1,
            'twilio_cost' => 0,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
