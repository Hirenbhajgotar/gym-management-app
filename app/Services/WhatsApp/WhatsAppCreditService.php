<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Interfaces\CreditManagementInterface;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppUsageLog;
use Exception;

/**
 * Shared WhatsApp Credit Service.
 *
 * Credit logic is provider-agnostic (database operations).
 * Both Twilio and Meta will use this exact same service.
 */
class WhatsAppCreditService implements CreditManagementInterface
{
    /**
     * When true, checkAndDeduct is a no-op. Used by broadcast flows
     * where credits are deducted upfront at the orchestration layer.
     */
    private bool $skipDeduction = false;

    public function __construct(
        protected int $gymId
    ) {}

    /**
     * Enable or disable skip-deduction mode.
     * When enabled, checkAndDeduct() becomes a no-op.
     */
    public function setSkipDeduction(bool $skip = true): void
    {
        $this->skipDeduction = $skip;
    }

    public function getCreditBalance(): array
    {
        $credit = WhatsAppMessageCredit::query()->where('gym_id', $this->gymId)->first();

        if (! $credit) {
            return [
                'total_credits' => 0,
                'used_credits' => 0,
                'remaining_credits' => 0,
                'welcome_credits_remaining' => 0,
                'can_send' => false,
                'usage_percentage' => 0,
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

    public function getMonthlyUsage(int $year, int $month): array
    {
        $logs = WhatsAppUsageLog::query()->where('gym_id', $this->gymId)
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
     * Check and deduct credits. Throws exception if insufficient.
     * No-op when skipDeduction is true (broadcast flow with upfront deduction).
     */
    public function checkAndDeduct(int $count = 1): void
    {
        if ($this->skipDeduction) {
            return;
        }

        $credit = WhatsAppMessageCredit::query()->where('gym_id', $this->gymId)->first();

        throw_if(! $credit || ! $credit->canSendMessage($count), Exception::class, 'Insufficient message credits. Please purchase more credits.');

        if ($credit->hasWelcomeCredits() && $credit->getWelcomeCreditsRemaining() >= $count) {
            $credit->useWelcomeCredit();
        } else {
            $credit->deductCredits($count);
        }
    }

    /**
     * Log message usage to database.
     */
    public function logUsage(string $recipient, ?WhatsAppTemplate $template, ?string $messageId, float $cost = 0): void
    {
        WhatsAppUsageLog::query()->create([
            'gym_id' => $this->gymId,
            'whatsapp_template_id' => $template?->id,
            'twilio_message_sid' => $messageId, // Column name remains for backward compat
            'recipient' => $recipient,
            'credits_used' => 1,
            'twilio_cost' => $cost,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
