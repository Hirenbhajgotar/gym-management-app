<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\WhatsAppCreditTransactionEnum;
use App\Policies\WhatsAppTransactionPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gym_id
 * @property int|null $whatsapp_message_credit_id
 * @property int|null $whatsapp_package_id
 * @property WhatsAppCreditTransactionEnum|null $type
 * @property int|null $credits
 * @property int|null $balance_before
 * @property int|null $balance_after
 * @property numeric|null $amount
 * @property string|null $payment_reference
 * @property string|null $description
 * @property int|null $admin_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WhatsAppMessageCredit|null $credit
 * @property-read Gym $gym
 * @property-read WhatsAppPackage|null $package
 * @property-read numeric|null $phone_number_surcharge
 *
 * @method static Builder<static>|WhatsAppCreditTransaction newModelQuery()
 * @method static Builder<static>|WhatsAppCreditTransaction newQuery()
 * @method static Builder<static>|WhatsAppCreditTransaction query()
 * @method static Builder<static>|WhatsAppCreditTransaction whereAdminId($value)
 * @method static Builder<static>|WhatsAppCreditTransaction whereAmount($value)
 * @method static Builder<static>|WhatsAppCreditTransaction whereBalanceAfter($value)
 * @method static Builder<static>|WhatsAppCreditTransaction whereBalanceBefore($value)
 * @method static Builder<static>|WhatsAppCreditTransaction whereCreatedAt($value)
 * @method static Builder<static>|WhatsAppCreditTransaction whereCredits($value)
 * @method static Builder<static>|WhatsAppCreditTransaction whereDescription($value)
 * @method static Builder<static>|WhatsAppCreditTransaction whereGymId($value)
 * @method static Builder<static>|WhatsAppCreditTransaction whereId($value)
 * @method static Builder<static>|WhatsAppCreditTransaction wherePaymentReference($value)
 * @method static Builder<static>|WhatsAppCreditTransaction whereType($value)
 * @method static Builder<static>|WhatsAppCreditTransaction whereUpdatedAt($value)
 * @method static Builder<static>|WhatsAppCreditTransaction whereWhatsappMessageCreditId($value)
 * @method static Builder<static>|WhatsAppCreditTransaction whereWhatsappPackageId($value)
 *
 * @mixin \Eloquent
 */
#[UsePolicy(WhatsAppTransactionPolicy::class)]
class WhatsAppCreditTransaction extends Model
{
    use HasFactory;
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
        'phone_number_surcharge',
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
            'phone_number_surcharge' => 'decimal:2',
            'type' => WhatsAppCreditTransactionEnum::class,
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

    /**
     * @return BelongsTo<WhatsAppMessageCredit, $this>
     */
    public function credit(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessageCredit::class, 'whatsapp_message_credit_id');
    }

    /**
     * @return BelongsTo<WhatsAppPackage, $this>
     */
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
