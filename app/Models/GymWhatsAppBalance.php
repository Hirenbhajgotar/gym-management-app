<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
