<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $gym_id
 * @property int|null $total_credits
 * @property int|null $used_credits
 * @property int|null $remaining_credits
 * @property numeric|null $cost_per_message
 * @property numeric|null $selling_price_per_msg
 * @property int|null $welcome_credits_used
 * @property bool|null $welcome_credits_claimed
 * @property bool $is_low_credit_notified
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Gym $gym
 *
 * @method static Builder<static>|WhatsAppMessageCredit newModelQuery()
 * @method static Builder<static>|WhatsAppMessageCredit newQuery()
 * @method static Builder<static>|WhatsAppMessageCredit query()
 * @method static Builder<static>|WhatsAppMessageCredit whereCostPerMessage($value)
 * @method static Builder<static>|WhatsAppMessageCredit whereCreatedAt($value)
 * @method static Builder<static>|WhatsAppMessageCredit whereGymId($value)
 * @method static Builder<static>|WhatsAppMessageCredit whereId($value)
 * @method static Builder<static>|WhatsAppMessageCredit whereIsLowCreditNotified($value)
 * @method static Builder<static>|WhatsAppMessageCredit whereLastUsedAt($value)
 * @method static Builder<static>|WhatsAppMessageCredit whereRemainingCredits($value)
 * @method static Builder<static>|WhatsAppMessageCredit whereSellingPricePerMsg($value)
 * @method static Builder<static>|WhatsAppMessageCredit whereTotalCredits($value)
 * @method static Builder<static>|WhatsAppMessageCredit whereUpdatedAt($value)
 * @method static Builder<static>|WhatsAppMessageCredit whereUsedCredits($value)
 * @method static Builder<static>|WhatsAppMessageCredit whereWelcomeCreditsClaimed($value)
 * @method static Builder<static>|WhatsAppMessageCredit whereWelcomeCreditsUsed($value)
 * @method bool hasWelcomeCredits()
 *
 * @mixin \Eloquent
 */
class WhatsAppMessageCredit extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_message_credits';

    protected $fillable = [
        'gym_id',
        'total_credits',
        'used_credits',
        'remaining_credits',
        'cost_per_message',
        'selling_price_per_msg',
        'welcome_credits_used',
        'welcome_credits_claimed',
        'is_low_credit_notified',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'total_credits' => 'integer',
            'used_credits' => 'integer',
            'remaining_credits' => 'integer',
            'cost_per_message' => 'decimal:4',
            'selling_price_per_msg' => 'decimal:4',
            'welcome_credits_used' => 'integer',
            'welcome_credits_claimed' => 'boolean',
            'is_low_credit_notified' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    // =========================
    // Relationships
    // =========================
    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    // =========================
    // Helper Methods
    // =========================

    public function canSendMessage(int $count = 1): bool
    {
        return $this->remaining_credits >= $count;
    }

    public function getWelcomeCreditsRemaining(): int
    {
        if (! $this->welcome_credits_claimed) {
            return 0;
        }

        $welcomeCredits = (int) config('services.whatsapp.welcome_credits', 50);

        return max(0, $welcomeCredits - ($this->welcome_credits_used ?? 0));
    }

    public function hasWelcomeCredits(): bool
    {
        return $this->getWelcomeCreditsRemaining() > 0;
    }

    public function useWelcomeCredit(int $count = 1): bool
    {
        $count = max(1, min($count, $this->getWelcomeCreditsRemaining()));

        if ($this->hasWelcomeCredits()) {
            $this->increment('welcome_credits_used', $count);
            $this->decrement('remaining_credits', $count);
            $this->update(['last_used_at' => now()]);

            return true;
        }

        return false;
    }

    public function deductCredits(int $count = 1): bool
    {
        if (! $this->canSendMessage($count)) {
            return false;
        }

        $this->increment('used_credits', $count);
        $this->decrement('remaining_credits', $count);
        $this->update(['last_used_at' => now()]);

        return true;
    }

    public function addCredits(int $count): void
    {
        $this->increment('total_credits', $count);
        $this->increment('remaining_credits', $count);
    }

    public function getUsagePercentage(): float
    {
        if ($this->total_credits <= 0) {
            return 0;
        }

        return (($this->used_credits ?? 0) / $this->total_credits) * 100;
    }

    public function getBalance(): float
    {
        return (float) ($this->remaining_credits ?? 0) * (float) ($this->selling_price_per_msg ?? 0);
    }
}
