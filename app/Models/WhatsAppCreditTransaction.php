<?php

namespace App\Models;

use App\Enums\WhatsAppCreditTransactionEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gym_id
 * @property int|null $whatsapp_message_credit_id
 * @property int|null $whatsapp_package_id
 * @property string $type
 * @property int $credits
 * @property int $balance_before
 * @property int $balance_after
 * @property float|null $amount
 * @property string|null $payment_reference
 * @property string|null $description
 * @property int|null $admin_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\WhatsAppMessageCredit|null $credit
 * @property-read \App\Models\WhatsAppPackage|null $package
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppCreditTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppCreditTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppCreditTransaction query()
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppCreditTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppCreditTransaction whereCredits($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppCreditTransaction whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppCreditTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppCreditTransaction whereType($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperWhatsAppCreditTransaction
 */
class WhatsAppCreditTransaction extends Model
{
    protected $table = 'whatsapp_credit_transactions';

    protected $fillable = [
        'gym_id',
        'whatsapp_message_credit_id',
        'whatsapp_package_id',
        'type',
        'credits',
        'balance_before',
        'balance_after',
        'amount',
        'payment_reference',
        'description',
        'admin_id',
    ];

    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
            'amount' => 'decimal:2',
            'type' => WhatsAppCreditTransactionEnum::class,
        ];
    }

    // =========================
    // Relationships
    // =========================

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    public function credit(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessageCredit::class, 'whatsapp_message_credit_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(WhatsAppPackage::class, 'whatsapp_package_id');
    }

    // =========================
    // Helper Methods
    // =========================

    public function isPurchase(): bool
    {
        return $this->type === WhatsAppCreditTransactionEnum::PURCHASE;
    }

    public function isUsage(): bool
    {
        return $this->type === WhatsAppCreditTransactionEnum::USAGE;
    }

    public function isRefund(): bool
    {
        return $this->type === WhatsAppCreditTransactionEnum::REFUND;
    }

    public function isAdjustment(): bool
    {
        return $this->type === WhatsAppCreditTransactionEnum::ADJUSTMENT;
    }

    public function isExpired(): bool
    {
        return $this->type === WhatsAppCreditTransactionEnum::EXPIRED;
    }

    public function isBonus(): bool
    {
        return $this->type === WhatsAppCreditTransactionEnum::BONUS;
    }
}
