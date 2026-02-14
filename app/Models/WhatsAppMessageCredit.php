<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gym_id
 * @property int $total_credits
 * @property int $used_credits
 * @property int $remaining_credits
 * @property float $cost_per_message
 * @property float $selling_price_per_msg
 * @property int $welcome_credits_used
 * @property bool $welcome_credits_claimed
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 *
 * @method static \Illuminate\Database\Eloquent\Eloquent\Builder<static>|WhatsAppMessageCredit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Eloquent\Builder<static>|WhatsAppMessageCredit newQuery()
 * @method static \Illuminate\Database\Eloquent\Eloquent\Builder<static>|WhatsAppMessageCredit query()
 * @method static \Illuminate\Database\Eloquent\Eloquent\Builder<static>|WhatsAppMessageCredit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent\Builder<static>|WhatsAppMessageCredit whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppMessageCredit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppMessageCredit whereRemainingCredits($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppMessageCredit whereTotalCredits($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppMessageCredit whereUsedCredits($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperWhatsAppMessageCredit
 */
class WhatsAppMessageCredit extends Model
{
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
            'last_used_at' => 'datetime',
        ];
    }

    // =========================
    // Relationships
    // =========================

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
        $welcomeCredits = 50;

        return max(0, $welcomeCredits - $this->welcome_credits_used);
    }

    public function hasWelcomeCredits(): bool
    {
        return $this->getWelcomeCreditsRemaining() > 0;
    }

    public function useWelcomeCredit(): bool
    {
        if ($this->hasWelcomeCredits()) {
            $this->increment('welcome_credits_used');
            $this->increment('remaining_credits');
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

    public function claimWelcomeCredits(): void
    {
        if (! $this->welcome_credits_claimed) {
            $this->update([
                'welcome_credits_claimed' => true,
                'welcome_credits_used' => 0,
                'remaining_credits' => 50,
                'total_credits' => 50,
            ]);
        }
    }

    public function getUsagePercentage(): float
    {
        if ($this->total_credits <= 0) {
            return 0;
        }

        return ($this->used_credits / $this->total_credits) * 100;
    }
}
