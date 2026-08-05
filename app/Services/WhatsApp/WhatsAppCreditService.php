<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Interfaces\CreditManagementInterface;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppUsageLog;
use Exception;
use Illuminate\Support\Facades\DB;

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

    private ?bool $isSelfManaged = null;

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
     * Determine whether the gym's WhatsApp account is self-managed.
     * Self-managed accounts bypass credit checks, deductions, and usage logging.
     */
    private function isSelfManagedAccount(): bool
    {
        if ($this->isSelfManaged !== null) {
            return $this->isSelfManaged;
        }

        $this->isSelfManaged = WhatsAppAccount::query()
            ->where('gym_id', $this->gymId)
            ->selfManageBillingMode()
            ->exists();

        return $this->isSelfManaged;
    }

    /**
     * Check and deduct credits. Throws exception if insufficient.
     * No-op when skipDeduction is true (broadcast flow with upfront deduction).
     * No-op when account is self-managed (user pays Meta directly).
     */
    public function checkAndDeduct(int $count = 1): void
    {
        if ($this->skipDeduction) {
            return;
        }

        if ($this->isSelfManagedAccount()) {
            return;
        }

        DB::transaction(function () use ($count) {
            $credit = WhatsAppMessageCredit::query()
                ->where('gym_id', $this->gymId)
                ->lockForUpdate()
                ->first();

            throw_if(! $credit || ! $credit->canSendMessage($count), Exception::class, 'Insufficient message credits. Please purchase more credits.');

            if ($credit->hasWelcomeCredits()) {
                $welcomeUsed = min($credit->getWelcomeCreditsRemaining(), $count);
                if ($welcomeUsed > 0) {
                    $credit->useWelcomeCredit($welcomeUsed);
                    $count -= $welcomeUsed;
                }
            }

            if ($count > 0) {
                $credit->deductCredits($count);
            }
        }, 5);
    }

    public function refund(int $count = 1): void
    {
        if ($this->skipDeduction || $this->isSelfManagedAccount()) {
            return;
        }

        DB::transaction(function () use ($count) {
            $credit = WhatsAppMessageCredit::query()
                ->where('gym_id', $this->gymId)
                ->lockForUpdate()
                ->first();

            if (! $credit) {
                return;
            }

            $credit->increment('remaining_credits', $count);
            $credit->decrement('used_credits', $count);
        }, 5);
    }

    /**
     * Log message usage to database.
     * No-op when account is self-managed (user pays Meta directly).
     */
    public function logUsage(string $recipient, ?WhatsAppTemplate $template, ?string $messageId, float $cost = 0): void
    {
        if ($this->isSelfManagedAccount()) {
            return;
        }

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
