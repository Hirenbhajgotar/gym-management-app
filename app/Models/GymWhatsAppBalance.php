<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gym_id
 * @property int $balance_messages
 * @property int $total_messages_purchased
 * @property int $messages_used
 * @property bool $low_balance_alert_sent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read mixed $remaining_messages
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance whereBalanceMessages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance whereLowBalanceAlertSent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance whereMessagesUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance whereTotalMessagesPurchased($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymWhatsAppBalance whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperGymWhatsAppBalance
 */
class GymWhatsAppBalance extends Model
{
    protected $table = 'gym_whatsapp_balances';

    protected $fillable = [
        'gym_id',
        'balance_messages',
        'total_messages_purchased',
        'messages_used',
        'low_balance_alert_sent',
    ];

    protected function casts(): array
    {
        return [
            'balance_messages' => 'integer',
            'total_messages_purchased' => 'integer',
            'messages_used' => 'integer',
            'low_balance_alert_sent' => 'boolean',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    protected function remainingMessages(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => $attributes['balance_messages'] - $attributes['messages_used'],
        );
    }

    public function hasLowBalance(): bool
    {
        $threshold = (int) $this->gym->getSetting('low_balance_threshold', 15);

        return $this->remaining_messages <= $threshold;
    }

    public function canSendMessage(): bool
    {
        return $this->remaining_messages > 0;
    }

    public function decrementMessages(int $count = 1): bool
    {
        $updated = self::where('id', $this->id)
            ->whereRaw('balance_messages - messages_used >= ?', [$count])
            ->increment('messages_used', $count);

        return $updated > 0;
    }

    public function addMessages(int $count): void
    {
        $this->increment('balance_messages', $count);
        $this->increment('total_messages_purchased', $count);
        $this->low_balance_alert_sent = false;
        $this->save();
    }
}
