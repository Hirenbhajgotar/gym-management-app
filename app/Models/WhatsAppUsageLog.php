<?php

namespace App\Models;

use App\Enums\WhatsAppUsageLogStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gym_id
 * @property int|null $whatsapp_template_id
 * @property string|null $twilio_message_sid
 * @property string $recipient
 * @property int|null $credits_used
 * @property numeric|null $twilio_cost
 * @property WhatsAppUsageLogStatusEnum|null $status
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\WhatsAppTemplate|null $template
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppUsageLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppUsageLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppUsageLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppUsageLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppUsageLog whereCreditsUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppUsageLog whereDeliveredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppUsageLog whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppUsageLog whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppUsageLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppUsageLog whereRecipient($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppUsageLog whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppUsageLog whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppUsageLog whereTwilioCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppUsageLog whereTwilioMessageSid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppUsageLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppUsageLog whereWhatsappTemplateId($value)
 * @mixin \Eloquent
 */
class WhatsAppUsageLog extends Model
{
    protected $table = 'whatsapp_usage_logs';

    protected $fillable = [
        'gym_id',
        'whatsapp_template_id',
        'twilio_message_sid',
        'recipient',
        'credits_used',
        'twilio_cost',
        'status',
        'error_message',
        'sent_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'credits_used' => 'integer',
            'twilio_cost' => 'decimal:4',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'status' => WhatsAppUsageLogStatusEnum::class,
        ];
    }

    // =========================
    // Relationships
    // =========================

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    // =========================
    // Helper Methods
    // =========================

    public function isDelivered(): bool
    {
        return $this->status === WhatsAppUsageLogStatusEnum::DELIVERED;
    }

    public function isFailed(): bool
    {
        return $this->status === WhatsAppUsageLogStatusEnum::FAILED;
    }

    public function isSent(): bool
    {
        return $this->status === WhatsAppUsageLogStatusEnum::SENT;
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => WhatsAppUsageLogStatusEnum::DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => WhatsAppUsageLogStatusEnum::FAILED,
            'error_message' => $error,
        ]);
    }
}
