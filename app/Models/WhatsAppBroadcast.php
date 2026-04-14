<?php

namespace App\Models;

use App\Enums\WhatsAppBroadcastStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $gym_id
 * @property int $whatsapp_template_id
 * @property string $title
 * @property string|null $description
 * @property array<array-key, mixed>|null $target_filters
 * @property int $target_count
 * @property WhatsAppBroadcastStatusEnum|null $status
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int $sent_count
 * @property int $failed_count
 * @property int $credits_used
 * @property array<array-key, mixed>|null $message_variables
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WhatsAppBroadcastRecipient> $recipients
 * @property-read int|null $recipients_count
 * @property-read \App\Models\WhatsAppTemplate $template
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast whereCreditsUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast whereFailedCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast whereMessageVariables($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast whereScheduledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast whereSentCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast whereTargetCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast whereTargetFilters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppBroadcast whereWhatsappTemplateId($value)
 * @mixin \Eloquent
 */
class WhatsAppBroadcast extends Model
{
    protected $table = 'whatsapp_broadcasts';

    protected $fillable = [
        'gym_id',
        'whatsapp_template_id',
        'title',
        'description',
        'target_filters',
        'target_count',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'sent_count',
        'failed_count',
        'credits_used',
        'message_variables',
    ];

    protected function casts(): array
    {
        return [
            'target_filters' => 'array',
            'target_count' => 'integer',
            'status' => WhatsAppBroadcastStatusEnum::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'credits_used' => 'integer',
            'message_variables' => 'array',
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

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsAppBroadcastRecipient::class, 'whatsapp_broadcast_id');
    }

    // =========================
    // Helper Methods
    // =========================

    public function isDraft(): bool
    {
        return $this->status === WhatsAppBroadcastStatusEnum::DRAFT;
    }

    public function isScheduled(): bool
    {
        return $this->status === WhatsAppBroadcastStatusEnum::SCHEDULED;
    }

    public function isSending(): bool
    {
        return $this->status === WhatsAppBroadcastStatusEnum::SENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === WhatsAppBroadcastStatusEnum::COMPLETED;
    }

    public function markAsSending(): void
    {
        $this->update([
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => WhatsAppBroadcastStatusEnum::COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function markAsCancelled(): void
    {
        $this->update(['status' => WhatsAppBroadcastStatusEnum::CANCELLED]);
    }

    public function incrementSentCount(): void
    {
        $this->increment('sent_count');
        $this->increment('credits_used');
    }

    public function incrementFailedCount(): void
    {
        $this->increment('failed_count');
    }

    public function getDeliveryRate(): float
    {
        $total = $this->sent_count + $this->failed_count;

        if ($total === 0) {
            return 0;
        }

        return ($this->sent_count / $total) * 100;
    }
}
