<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WhatsAppBroadcastRecipientStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $whatsapp_broadcast_id
 * @property int $member_id
 * @property string $phone_number
 * @property string $member_name
 * @property WhatsAppBroadcastRecipientStatusEnum|null $status
 * @property string|null $twilio_message_sid
 * @property string|null $failure_reason
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WhatsAppBroadcast $broadcast
 * @property-read GymMember $member
 *
 * @method static Builder<static>|WhatsAppBroadcastRecipient newModelQuery()
 * @method static Builder<static>|WhatsAppBroadcastRecipient newQuery()
 * @method static Builder<static>|WhatsAppBroadcastRecipient query()
 * @method static Builder<static>|WhatsAppBroadcastRecipient whereCreatedAt($value)
 * @method static Builder<static>|WhatsAppBroadcastRecipient whereDeliveredAt($value)
 * @method static Builder<static>|WhatsAppBroadcastRecipient whereFailureReason($value)
 * @method static Builder<static>|WhatsAppBroadcastRecipient whereId($value)
 * @method static Builder<static>|WhatsAppBroadcastRecipient whereMemberId($value)
 * @method static Builder<static>|WhatsAppBroadcastRecipient whereMemberName($value)
 * @method static Builder<static>|WhatsAppBroadcastRecipient wherePhoneNumber($value)
 * @method static Builder<static>|WhatsAppBroadcastRecipient whereSentAt($value)
 * @method static Builder<static>|WhatsAppBroadcastRecipient whereStatus($value)
 * @method static Builder<static>|WhatsAppBroadcastRecipient whereTwilioMessageSid($value)
 * @method static Builder<static>|WhatsAppBroadcastRecipient whereUpdatedAt($value)
 * @method static Builder<static>|WhatsAppBroadcastRecipient whereWhatsappBroadcastId($value)
 *
 * @mixin \Eloquent
 */
class WhatsAppBroadcastRecipient extends Model
{
    use HasFactory;

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
    /**
     * @return BelongsTo<WhatsAppBroadcast, $this>
     */
    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(WhatsAppBroadcast::class, 'whatsapp_broadcast_id');
    }

    /**
     * @return BelongsTo<GymMember, $this>
     */
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
