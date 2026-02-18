<?php

namespace App\Models;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Observers\WhatsAppTemplateObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gym_id
 * @property string $name
 * @property string $display_name
 * @property string $body
 * @property string $category
 * @property array|null $variables
 * @property int $variable_count
 * @property string|null $twilio_template_sid
 * @property string|null $meta_template_id
 * @property string $status
 * @property string|null $rejection_reason
 * @property bool $is_default
 * @property bool $is_active
 * @property int $usage_count
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhatsAppTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Eloquent\Builder<static>|WhatsAppTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Eloquent\Builder<static>|WhatsAppTemplate query()
 * @method static \Illuminate\Database\Eloquent\Eloquent\Builder<static>|WhatsAppTemplate whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent\Builder<static>|WhatsAppTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent\Builder<static>|WhatsAppTemplate whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent\Builder<static>|WhatsAppTemplate whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent\Builder<static>|WhatsAppTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent\Builder<static>|WhatsAppTemplate whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent\Builder<static>|WhatsAppTemplate whereName($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent\Builder<static>|WhatsAppTemplate whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Eloquent\Builder<static>|WhatsAppTemplate whereTwilioTemplateSid($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperWhatsAppTemplate
 */
#[ObservedBy([WhatsAppTemplateObserver::class])]
class WhatsAppTemplate extends Model
{
    const NAVIGATION_BADGE_CACHE_KEY = 'whatsapp_template_navigation_badge_';

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'gym_id',
        'name',
        'display_name',
        'body',
        'category',
        'variables',
        'variable_count',
        'twilio_template_sid',
        'meta_template_id',
        'status',
        'rejection_reason',
        'is_default',
        'is_active',
        'usage_count',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'variable_count' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'usage_count' => 'integer',
            'last_used_at' => 'datetime',
            'status' => WhatsAppTemplateStatusEnum::class,
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

    public function isApproved(): bool
    {
        return $this->status === WhatsAppTemplateStatusEnum::APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === WhatsAppTemplateStatusEnum::PENDING;
    }

    public function isDraft(): bool
    {
        return $this->status === WhatsAppTemplateStatusEnum::DRAFT;
    }

    public function isRejected(): bool
    {
        return $this->status === WhatsAppTemplateStatusEnum::REJECTED;
    }

    public function canBeUsed(): bool
    {
        return $this->isApproved() && $this->is_active;
    }

    public function markAsApproved(string $twilioSid, string $metaId): void
    {
        $this->update([
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
            'twilio_template_sid' => $twilioSid,
            'meta_template_id' => $metaId,
        ]);
    }

    public function markAsRejected(string $reason): void
    {
        $this->update([
            'status' => WhatsAppTemplateStatusEnum::REJECTED,
            'rejection_reason' => $reason,
        ]);
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    public function getVariableNames(): array
    {
        if (! $this->variables) {
            return [];
        }

        return collect($this->variables)->pluck('name')->toArray();
    }

    public static function getDefaults(): Collection
    {
        return self::where('is_default', true)->whereNull('gym_id')->get();
    }
}
