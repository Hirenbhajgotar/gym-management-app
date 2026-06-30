<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WhatsAppUsageLogStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Gym $gym
 * @property-read WhatsAppTemplate|null $template
 *
 * @method static Builder<static>|WhatsAppUsageLog newModelQuery()
 * @method static Builder<static>|WhatsAppUsageLog newQuery()
 * @method static Builder<static>|WhatsAppUsageLog query()
 * @method static Builder<static>|WhatsAppUsageLog whereCreatedAt($value)
 * @method static Builder<static>|WhatsAppUsageLog whereCreditsUsed($value)
 * @method static Builder<static>|WhatsAppUsageLog whereDeliveredAt($value)
 * @method static Builder<static>|WhatsAppUsageLog whereErrorMessage($value)
 * @method static Builder<static>|WhatsAppUsageLog whereGymId($value)
 * @method static Builder<static>|WhatsAppUsageLog whereId($value)
 * @method static Builder<static>|WhatsAppUsageLog whereRecipient($value)
 * @method static Builder<static>|WhatsAppUsageLog whereSentAt($value)
 * @method static Builder<static>|WhatsAppUsageLog whereStatus($value)
 * @method static Builder<static>|WhatsAppUsageLog whereTwilioCost($value)
 * @method static Builder<static>|WhatsAppUsageLog whereTwilioMessageSid($value)
 * @method static Builder<static>|WhatsAppUsageLog whereUpdatedAt($value)
 * @method static Builder<static>|WhatsAppUsageLog whereWhatsappTemplateId($value)
 *
 * @mixin \Eloquent
 */
class WhatsAppUsageLog extends Model
{
    use HasFactory;

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
    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    /**
     * @return BelongsTo<WhatsAppTemplate, $this>
     */
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
