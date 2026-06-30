<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WhatsAppCreditTransactionEnum;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppCreditTransaction;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppPackage;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppBillingService
{
    // Lock timeout: 10 seconds (enough for DB operations)
    // Block timeout: 5 seconds (wait for lock)
    private const LOCK_TIMEOUT = 10;

    private const BLOCK_TIMEOUT = 5;

    protected RazorpayService $razorpayService;

    public function __construct(RazorpayService $razorpayService)
    {
        $this->razorpayService = $razorpayService;
    }

    public function purchasePackage(int $gymId, int $packageId, string $paymentReference, ?int $userId = null, float $phoneNumberSurcharge = 0.0): array
    {
        $package = WhatsAppPackage::query()->findOrFail($packageId);

        if (! $package->is_active) {
            return [
                'success' => false,
                'error' => 'Package is not available',
            ];
        }

        $lockKey = "billing:whatsapp-package:purchase:{$gymId}";

        try {
            return Cache::lock($lockKey, self::LOCK_TIMEOUT)->block(self::BLOCK_TIMEOUT, function () use ($gymId, $package, $paymentReference, $phoneNumberSurcharge) {
                return DB::transaction(function () use ($gymId, $package, $paymentReference, $phoneNumberSurcharge) {
                    $credit = $this->getOrCreateCredit($gymId, $package->selling_price / $package->message_count);
                    $balanceBefore = $credit->remaining_credits;

                    $credit->increment('total_credits', $package->message_count);
                    $credit->increment('remaining_credits', $package->message_count);

                    $this->resetLowCreditFlagIfNeeded($credit);

                    WhatsAppCreditTransaction::query()->create([
                        'gym_id' => $gymId,
                        'whatsapp_message_credit_id' => $credit->id,
                        'whatsapp_package_id' => $package->id,
                        'type' => WhatsAppCreditTransactionEnum::PURCHASE,
                        'credits' => $package->message_count,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $credit->remaining_credits,
                        'amount' => $package->selling_price,
                        'phone_number_surcharge' => $phoneNumberSurcharge > 0 ? $phoneNumberSurcharge : 0.00,
                        'payment_reference' => $paymentReference,
                        'description' => "Purchased {$package->name} package",
                        'admin_id' => null,
                    ]);

                    return [
                        'success' => true,
                        'package_name' => $package->name,
                        'message_count' => $package->message_count,
                        'total_credits' => $credit->total_credits,
                        'remaining_credits' => $credit->remaining_credits,
                        'phone_number_surcharge' => $phoneNumberSurcharge,
                    ];
                });
            });
        } catch (LockTimeoutException $e) {
            return ['success' => false, 'error' => 'Payment is being processed. Please wait.'];
        } catch (Exception $e) {
            Log::error('Package purchase failed', ['gym_id' => $gymId, 'error' => $e->getMessage()]);

            return ['success' => false, 'error' => 'Failed to process package purchase.'];
        }
    }

    public function getTwilioNumberSurcharge(int $gymId): array
    {
        $account = WhatsAppAccount::query()->where('gym_id', $gymId)->with('phoneNumber')->first();

        if (! $account || ! $account->isTwilioWithActiveNumber()) {
            return [
                'eligible' => false,
                'amount' => 0.0,
                'phone' => null,
            ];
        }

        $phone = $account->phoneNumber;

        if (! $phone->isSurchargeDue()) {
            return [
                'eligible' => false,
                'amount' => 0.0,
                'phone' => $phone,
            ];
        }

        return [
            'eligible' => true,
            'amount' => $phone->getSurchargeAmount(),
            'phone' => $phone,
        ];
    }

    public function addWelcomeCredits(int $gymId): void
    {
        $lockKey = "billing:welcome:{$gymId}";

        try {
            Cache::lock($lockKey, self::LOCK_TIMEOUT)->block(self::BLOCK_TIMEOUT, function () use ($gymId) {
                DB::transaction(function () use ($gymId) {
                    $credit = $this->getOrCreateCredit($gymId);

                    if ($credit->welcome_credits_claimed) {
                        return; // Exit transaction silently
                    }

                    $welcomeCredits = config('services.whatsapp.welcome_credits', 50);
                    $balanceBefore = $credit->remaining_credits;

                    $credit->update([
                        'welcome_credits_claimed' => true,
                        'welcome_credits_used' => 0,
                        'remaining_credits' => $balanceBefore + $welcomeCredits,
                        'total_credits' => $credit->total_credits + $welcomeCredits,
                        'is_low_credit_notified' => false,
                    ]);

                    WhatsAppCreditTransaction::query()->create([
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
                    ]);
                });
            });
        } catch (LockTimeoutException $e) {
            // Silent fail, user will just see 0 credits and can retry
            Log::warning('Welcome credits lock timeout', ['gym_id' => $gymId]);
        } catch (Exception $e) {
            Log::error('Welcome credits usage failed', ['gym_id' => $gymId, 'error' => $e->getMessage()]);
        }
    }

    public function hasClaimedWelcomeCredits(int $gymId): bool
    {
        return WhatsAppMessageCredit::query()->where('gym_id', $gymId)->value('welcome_credits_claimed') ?? false;
    }

    public function useCredits(int $gymId, int $credits, string $description): bool
    {
        $lockKey = "billing:usage:{$gymId}";

        try {
            return Cache::lock($lockKey, self::LOCK_TIMEOUT)->block(self::BLOCK_TIMEOUT, function () use ($gymId, $credits, $description) {
                $credit = WhatsAppMessageCredit::query()->where('gym_id', $gymId)->lockForUpdate()->first();

                if (! $credit || ! $credit->canSendMessage($credits)) {
                    return false;
                }

                $balanceBefore = $credit->remaining_credits;

                $credit->increment('used_credits', $credits);
                $credit->decrement('remaining_credits', $credits);

                WhatsAppCreditTransaction::query()->create([
                    'gym_id' => $gymId,
                    'whatsapp_message_credit_id' => $credit->id,
                    'type' => 'usage',
                    'credits' => -$credits,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $credit->remaining_credits,
                    'description' => $description,
                ]);

                return true;
            });
        } catch (LockTimeoutException $e) {
            // Silent fail, user will just see 0 credits and can retry
            Log::warning('Credit usage lock timeout', ['gym_id' => $gymId]);

            return false;
        } catch (Exception $e) {
            Log::error('Credit usage failed', ['gym_id' => $gymId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function refundCredits(int $gymId, int $credits, string $reason, ?int $adminId = null): void
    {
        $this->adjustCredits($gymId, $credits, $reason, $adminId, WhatsAppCreditTransactionEnum::REFUND);
    }

    public function adjustCredits(int $gymId, int $credits, string $description, ?int $adminId = null, WhatsAppCreditTransactionEnum $type = WhatsAppCreditTransactionEnum::ADJUSTMENT): void
    {
        $lockKey = "billing:credits:adjust:{$gymId}";

        try {
            Cache::lock($lockKey, self::LOCK_TIMEOUT)->block(self::BLOCK_TIMEOUT, function () use ($gymId, $credits, $description, $adminId, $type) {
                DB::transaction(function () use ($gymId, $credits, $description, $adminId, $type) {
                    $credit = WhatsAppMessageCredit::query()->where('gym_id', $gymId)->first();

                    if (! $credit) {
                        return;
                    }

                    $balanceBefore = $credit->remaining_credits;

                    if ($credits >= 0) {
                        $credit->increment('total_credits', $credits);
                        $credit->increment('remaining_credits', $credits);

                        $this->resetLowCreditFlagIfNeeded($credit);
                    } else {
                        $credit->decrement('total_credits', abs($credits));
                        $credit->decrement('remaining_credits', abs($credits));
                    }

                    WhatsAppCreditTransaction::query()->create([
                        'gym_id' => $gymId,
                        'whatsapp_message_credit_id' => $credit->id,
                        'type' => $type,
                        'credits' => $credits,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $credit->remaining_credits,
                        'description' => $description,
                        'admin_id' => $adminId,
                    ]);
                });
            });
        } catch (LockTimeoutException $e) {
            throw new Exception('System is busy processing another credit adjustment. Please try again.');
        }
    }

    public function getCreditBalance(int $gymId): array
    {
        $credit = WhatsAppMessageCredit::query()->where('gym_id', $gymId)->first();

        if (! $credit) {
            return [
                'total_credits' => 0,
                'used_credits' => 0,
                'remaining_credits' => 0,
                'welcome_credits_remaining' => 50,
                'usage_percentage' => 0,
            ];
        }

        return [
            'total_credits' => $credit->total_credits,
            'used_credits' => $credit->used_credits,
            'remaining_credits' => $credit->remaining_credits,
            'welcome_credits_remaining' => $credit->hasWelcomeCredits() ? $credit->getWelcomeCreditsRemaining() : 0,
            'usage_percentage' => $credit->getUsagePercentage(),
            'can_send' => $credit->canSendMessage(),
        ];
    }

    public function getTransactionHistory(int $gymId, int $limit = 50): Collection
    {
        return WhatsAppCreditTransaction::query()->where('gym_id', $gymId)->latest()
            ->limit($limit)
            ->get();
    }

    public function getUsageReport(int $gymId, Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = "billing:usage-report:{$gymId}:" . $startDate->format('Y-m-d') . ':' . $endDate->format('Y-m-d');

        return Cache::flexible($cacheKey, [now()->addMinutes(10), now()->addMinutes(30)], function () use ($gymId, $startDate, $endDate) {
            $transactions = WhatsAppCreditTransaction::query()->where('gym_id', $gymId)
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
        });
    }

    // =========================================================
    // PRIVATE HELPERS
    // =========================================================

    private function getOrCreateCredit(int $gymId, float $sellingPrice = 0.25): WhatsAppMessageCredit
    {
        return WhatsAppMessageCredit::query()->firstOrCreate(['gym_id' => $gymId], [
            'total_credits' => 0,
            'used_credits' => 0,
            'remaining_credits' => 0,
            'cost_per_message' => 0.15,
            'selling_price_per_msg' => $sellingPrice,
        ]);
    }

    private function resetLowCreditFlagIfNeeded(WhatsAppMessageCredit $credit): void
    {
        if ($credit->getBalance() > 5) {
            $credit->update(['is_low_credit_notified' => false]);
        }
    }
}
