<?php

namespace App\Services;

use App\Enums\WhatsAppCreditTransactionEnum;
use App\Models\WhatsAppCreditTransaction;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppPackage;
use Illuminate\Support\Facades\Log;

class WhatsAppBillingService
{
    protected RazorpayService $razorpayService;

    public function __construct(RazorpayService $razorpayService)
    {
        $this->razorpayService = $razorpayService;
    }

    public function purchasePackage(int $gymId, int $packageId, string $paymentReference, ?int $userId = null): array
    {
        $package = WhatsAppPackage::findOrFail($packageId);

        if (! $package->is_active) {
            return [
                'success' => false,
                'error' => 'Package is not available',
            ];
        }

        $credit = WhatsAppMessageCredit::firstOrCreate(
            ['gym_id' => $gymId],
            [
                'total_credits' => 0,
                'used_credits' => 0,
                'remaining_credits' => 0,
                'cost_per_message' => 0.15,
                'selling_price_per_msg' => $package->selling_price / $package->message_count,
            ]
        );

        $balanceBefore = $credit->remaining_credits;

        $credit->increment('total_credits', $package->message_count);
        $credit->increment('remaining_credits', $package->message_count);

        WhatsAppCreditTransaction::create([
            'gym_id' => $gymId,
            'whatsapp_message_credit_id' => $credit->id,
            'whatsapp_package_id' => $package->id,
            'type' => WhatsAppCreditTransactionEnum::PURCHASE,
            'credits' => $package->message_count,
            'balance_before' => $balanceBefore,
            'balance_after' => $credit->remaining_credits,
            'amount' => $package->selling_price,
            'payment_reference' => $paymentReference,
            'description' => "Purchased {$package->name} package",
            'admin_id' => null,
        ]);

        Log::info('WhatsApp package purchased', [
            'gym_id' => $gymId,
            'package_id' => $package->id,
            'message_count' => $package->message_count,
            'amount' => $package->selling_price,
            'payment_reference' => $paymentReference,
        ]);

        return [
            'success' => true,
            'package_name' => $package->name,
            'message_count' => $package->message_count,
            'total_credits' => $credit->total_credits,
            'remaining_credits' => $credit->remaining_credits,
        ];
    }

    public function addWelcomeCredits(int $gymId): void
    {
        $credit = WhatsAppMessageCredit::firstOrCreate(
            ['gym_id' => $gymId],
            [
                'total_credits' => 0,
                'used_credits' => 0,
                'remaining_credits' => 0,
                'cost_per_message' => 0.15,
                'selling_price_per_msg' => 0.25,
            ]
        );

        if ($credit->welcome_credits_claimed) {
            return;
        }

        $welcomeCredits = 50;
        $balanceBefore = $credit->remaining_credits;

        $credit->update([
            'welcome_credits_claimed' => true,
            'welcome_credits_used' => 0,
            'remaining_credits' => $balanceBefore + $welcomeCredits,
            'total_credits' => $credit->total_credits + $welcomeCredits,
        ]);

        WhatsAppCreditTransaction::create([
            'gym_id' => $gymId,
            'whatsapp_message_credit_id' => $credit->id,
            'type' => WhatsAppCreditTransactionEnum::PURCHASE,
            'credits' => $welcomeCredits,
            'balance_before' => $balanceBefore,
            'balance_after' => $credit->remaining_credits,
            'amount' => 0,
            'description' => "Welcome credits ({$welcomeCredits} free messages)",
        ]);

        Log::info('Welcome credits added for gym', [
            'gym_id' => $gymId,
            'credits' => $welcomeCredits,
        ]);
    }

    public function hasClaimedWelcomeCredits(int $gymId): bool
    {
        $credit = WhatsAppMessageCredit::where('gym_id', $gymId)->first();

        return $credit ? $credit->welcome_credits_claimed : false;
    }

    public function useCredits(int $gymId, int $credits, string $description): bool
    {
        $credit = WhatsAppMessageCredit::where('gym_id', $gymId)->first();

        if (! $credit || ! $credit->canSendMessage($credits)) {
            return false;
        }

        $balanceBefore = $credit->remaining_credits;

        $credit->increment('used_credits', $credits);
        $credit->decrement('remaining_credits', $credits);

        WhatsAppCreditTransaction::create([
            'gym_id' => $gymId,
            'whatsapp_message_credit_id' => $credit->id,
            'type' => 'usage',
            'credits' => -$credits,
            'balance_before' => $balanceBefore,
            'balance_after' => $credit->remaining_credits,
            'description' => $description,
        ]);

        return true;
    }

    public function refundCredits(int $gymId, int $credits, string $reason, ?int $adminId = null): void
    {
        $credit = WhatsAppMessageCredit::where('gym_id', $gymId)->first();

        if (! $credit) {
            return;
        }

        $balanceBefore = $credit->remaining_credits;

        $credit->increment('total_credits', $credits);
        $credit->increment('remaining_credits', $credits);

        WhatsAppCreditTransaction::create([
            'gym_id' => $gymId,
            'whatsapp_message_credit_id' => $credit->id,
            'type' => 'refund',
            'credits' => $credits,
            'balance_before' => $balanceBefore,
            'balance_after' => $credit->remaining_credits,
            'description' => $reason,
            'admin_id' => $adminId,
        ]);

        Log::info('Credits refunded', [
            'gym_id' => $gymId,
            'credits' => $credits,
            'reason' => $reason,
        ]);
    }

    public function adjustCredits(int $gymId, int $credits, string $description, ?int $adminId = null): void
    {
        $credit = WhatsAppMessageCredit::where('gym_id', $gymId)->first();

        if (! $credit) {
            return;
        }

        $balanceBefore = $credit->remaining_credits;

        if ($credits >= 0) {
            $credit->increment('total_credits', $credits);
            $credit->increment('remaining_credits', $credits);
        } else {
            $credit->decrement('total_credits', abs($credits));
            $credit->decrement('remaining_credits', abs($credits));
        }

        WhatsAppCreditTransaction::create([
            'gym_id' => $gymId,
            'whatsapp_message_credit_id' => $credit->id,
            'type' => WhatsAppCreditTransactionEnum::ADJUSTMENT,
            'credits' => $credits,
            'balance_before' => $balanceBefore,
            'balance_after' => $credit->remaining_credits,
            'description' => $description,
            'admin_id' => $adminId,
        ]);
    }

    public function getCreditBalance(int $gymId): array
    {
        $credit = WhatsAppMessageCredit::where('gym_id', $gymId)->first();

        if (! $credit) {
            return [
                'total_credits' => 0,
                'used_credits' => 0,
                'remaining_credits' => 0,
                'welcome_credits_remaining' => 50,
                'usage_percentage' => 0,
            ];
        }

        $welcomeCreditsRemaining = $credit->hasWelcomeCredits()
            ? $credit->getWelcomeCreditsRemaining()
            : 0;

        return [
            'total_credits' => $credit->total_credits,
            'used_credits' => $credit->used_credits,
            'remaining_credits' => $credit->remaining_credits,
            'welcome_credits_remaining' => $welcomeCreditsRemaining,
            'usage_percentage' => $credit->getUsagePercentage(),
            'can_send' => $credit->canSendMessage(),
        ];
    }

    public function getTransactionHistory(int $gymId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return WhatsAppCreditTransaction::where('gym_id', $gymId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getUsageReport(int $gymId, \Carbon\Carbon $startDate, \Carbon\Carbon $endDate): array
    {
        $transactions = WhatsAppCreditTransaction::where('gym_id', $gymId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalPurchased = $transactions->where('type', WhatsAppCreditTransactionEnum::PURCHASE)->sum('credits');
        $totalUsed = abs($transactions->where('type', WhatsAppCreditTransactionEnum::USAGE)->sum('credits'));
        $totalRefunded = $transactions->where('type', WhatsAppCreditTransactionEnum::REFUND)->sum('credits');

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'total_purchased' => $totalPurchased,
            'total_used' => $totalUsed,
            'total_refunded' => $totalRefunded,
            'net_usage' => $totalUsed - $totalRefunded,
            'transaction_count' => $transactions->count(),
        ];
    }
}
