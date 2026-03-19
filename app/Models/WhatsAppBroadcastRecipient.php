<?php

namespace App\Models;

use App\Enums\WhatsAppBroadcastRecipientStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $whatsapp_broadcast_id
 * @property int $member_id
 * @property string $phone_number
 * @property string $member_name
 * @property string $status
 * @property string|null $twilio_message_sid
 * @property string|null $failure_reason
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\WhatsAppBroadcast $broadcast
 * @property-read \App\Models\Member $member
 *
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppBroadcastRecipient newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppBroadcastRecipient newQuery()
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppBroadcastRecipient query()
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppBroadcastRecipient whereBroadcastId($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppBroadcastRecipient whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppBroadcastRecipient whereId($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppBroadcastRecipient whereMemberId($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent Builder<static>|WhatsAppBroadcastRecipient whereStatus($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperWhatsAppBroadcastRecipient
 */
class WhatsAppBroadcastRecipient extends Model
{
    protected $table = 'whatsapp_broadcast_recipients';

    protected $fillable = [
        'whatsapp_broadcast_id',
        'member_id',
        'phone_number',
        'member_name',
        'status',
        'twilio_message_sid',
        'failure_reason',
        'sent_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WhatsAppBroadcastRecipientStatusEnum::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    // =========================
    // Relationships
    // =========================

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(WhatsAppBroadcast::class, 'whatsapp_broadcast_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(GymMember::class, 'member_id');
    }

    // =========================
    // Helper Methods
    // =========================

    public function isPending(): bool
    {
        return $this->status === WhatsAppBroadcastRecipientStatusEnum::PENDING;
    }

    public function isSending(): bool
    {
        return $this->status === WhatsAppBroadcastRecipientStatusEnum::SENDING;
    }

    public function isSent(): bool
    {
        return $this->status === WhatsAppBroadcastRecipientStatusEnum::SENT;
    }

    public function isDelivered(): bool
    {
        return $this->status === WhatsAppBroadcastRecipientStatusEnum::DELIVERED;
    }

    public function isFailed(): bool
    {
        return $this->status === WhatsAppBroadcastRecipientStatusEnum::FAILED;
    }

    public function markAsSent(string $messageSid): void
    {
        $this->update([
            'status' => WhatsAppBroadcastRecipientStatusEnum::SENT,
            'twilio_message_sid' => $messageSid,
            'sent_at' => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => WhatsAppBroadcastRecipientStatusEnum::DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => WhatsAppBroadcastRecipientStatusEnum::FAILED,
            'failure_reason' => $reason,
        ]);
    }
}
