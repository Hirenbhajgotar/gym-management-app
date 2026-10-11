<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TemplateCategoryEnum;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Observers\WhatsAppTemplateObserver;
use Database\Factories\WhatsAppTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $gym_id
 * @property string $name
 * @property string $display_name
 * @property string|null $body
 * @property TemplateCategoryEnum|null $category
 * @property string $language
 * @property array<array-key, mixed>|null $variables
 * @property int|null $variable_count
 * @property array<array-key, mixed>|null $variable_placeholder
 * @property string|null $twilio_template_sid
 * @property string|null $meta_template_id
 * @property WhatsAppTemplateStatusEnum|null $status
 * @property string|null $rejection_reason
 * @property bool|null $is_default
 * @property bool|null $is_active
 * @property int|null $usage_count
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string|null $formatted_body
 * @property-read Gym|null $gym
 *
 * @method static Builder<static>|WhatsAppTemplate isActive()
 * @method static Builder<static>|WhatsAppTemplate isDefault()
 * @method static Builder<static>|WhatsAppTemplate newModelQuery()
 * @method static Builder<static>|WhatsAppTemplate newQuery()
 * @method static Builder<static>|WhatsAppTemplate onlyTrashed()
 * @method static Builder<static>|WhatsAppTemplate query()
 * @method static Builder<static>|WhatsAppTemplate whereBody($value)
 * @method static Builder<static>|WhatsAppTemplate whereCategory($value)
 * @method static Builder<static>|WhatsAppTemplate whereCreatedAt($value)
 * @method static Builder<static>|WhatsAppTemplate whereDeletedAt($value)
 * @method static Builder<static>|WhatsAppTemplate whereDisplayName($value)
 * @method static Builder<static>|WhatsAppTemplate whereGymId($value)
 * @method static Builder<static>|WhatsAppTemplate whereId($value)
 * @method static Builder<static>|WhatsAppTemplate whereIsActive($value)
 * @method static Builder<static>|WhatsAppTemplate whereIsDefault($value)
 * @method static Builder<static>|WhatsAppTemplate whereLastUsedAt($value)
 * @method static Builder<static>|WhatsAppTemplate whereMetaTemplateId($value)
 * @method static Builder<static>|WhatsAppTemplate whereName($value)
 * @method static Builder<static>|WhatsAppTemplate whereRejectionReason($value)
 * @method static Builder<static>|WhatsAppTemplate whereStatus($value)
 * @method static Builder<static>|WhatsAppTemplate whereTwilioTemplateSid($value)
 * @method static Builder<static>|WhatsAppTemplate whereUpdatedAt($value)
 * @method static Builder<static>|WhatsAppTemplate whereUsageCount($value)
 * @method static Builder<static>|WhatsAppTemplate whereVariableCount($value)
 * @method static Builder<static>|WhatsAppTemplate whereVariablePlaceholder($value)
 * @method static Builder<static>|WhatsAppTemplate whereVariables($value)
 * @method static Builder<static>|WhatsAppTemplate withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|WhatsAppTemplate withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[ObservedBy([WhatsAppTemplateObserver::class])]
class WhatsAppTemplate extends Model
{
    /** @use HasFactory<WhatsAppTemplateFactory> */
    use HasFactory;

    use SoftDeletes;

    public const NAVIGATION_BADGE_CACHE_KEY = 'whatsapp_template_navigation_badge_';

    public const SUPER_ADMIN_NAVIGATION_BADGE_CACHE_KEY = 'super_admin_whatsapp_template_navigation_badge';

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        // Core form fields (both Admin and SuperAdmin panels)
        'gym_id',
        'name',
        'display_name',
        'body',
        'category',
        'language',
        // Computed/derived fields (explicitly set by services, not from form input)
        'variables',
        'variable_count',
        'variable_placeholder',
        // Form-submitted fields
        'status',      // SuperAdmin panel can set status on create/edit
        'is_active',   // Admin panel toggles this in Settings section
    ];

    // System/Internal fields (NOT fillable)
    // twilio_template_sid, meta_template_id, rejection_reason, is_default, usage_count, last_used_at
    // These are updated explicitly via direct property assignment (e.g., $record->field = value).

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'variable_count' => 'integer',
            'variable_placeholder' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'usage_count' => 'integer',
            'last_used_at' => 'datetime',
            'category' => TemplateCategoryEnum::class,
            'status' => WhatsAppTemplateStatusEnum::class,
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

    // =========================
    // Accessors
    // =========================

    /**
     * @return Attribute<string|null, void>
     */
    protected function formattedBody(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $body = $this->body;
                $placeholders = $this->variable_placeholder;

                if (blank($placeholders)) {
                    return $body;
                }

                $search = [];
                $replace = [];

                /** @var array<string, mixed> $placeholders */
                foreach ($placeholders as $key => $value) {
                    $search[] = "{{{$key}}}";

                    if (is_array($value)) {
                        $example = isset($value['example']) && is_string($value['example']) ? $value['example'] : null;
                        $placeholder = isset($value['placeholder']) && is_string($value['placeholder']) ? "{{{$value['placeholder']}}}" : "{{{$key}}}";
                        $replace[] = $example ?? $placeholder;
                    } else {
                        $replace[] = is_scalar($value) ? (string) $value : '';
                    }
                }

                return str_replace($search, $replace, (string) $body);
            },
        );
    }

    // =========================
    // Scopes
    // =========================

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function isDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function isActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', WhatsAppTemplateStatusEnum::APPROVED);
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
        return $this->isApproved() && (bool) $this->is_active;
    }

    public function markAsApproved(string $twilioSid, string $metaId): void
    {
        $this->status = WhatsAppTemplateStatusEnum::APPROVED;
        $this->twilio_template_sid = $twilioSid;
        $this->meta_template_id = $metaId;
        $this->save();
    }

    public function markAsRejected(string $reason): void
    {
        $this->status = WhatsAppTemplateStatusEnum::REJECTED;
        $this->rejection_reason = $reason;
        $this->save();
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count', 1, ['last_used_at' => now()]);
    }

    /**
     * @return array<int, string>
     */
    public function getVariableNames(): array
    {
        if (! $this->variables) {
            return [];
        }

        /** @var array<int, string> */
        return collect($this->variables)->pluck('name')->map(fn ($val) => is_scalar($val) ? (string) $val : '')->all();
    }

    public function getVariablePlaceholder(?string $index): ?string
    {
        if (! $this->variable_placeholder || ! $index) {
            return null;
        }

        $val = $this->variable_placeholder[$index] ?? null;

        return is_string($val) ? $val : null;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getVariablePlaceholders(): array
    {
        /** @var array<array-key, mixed> $placeholders */
        $placeholders = is_array($this->variable_placeholder) ? $this->variable_placeholder : [];

        return $placeholders;
    }

    /**
     * Validate that the provided variables satisfy the template's requirements.
     *
     * @param  array<array-key, mixed>  $variables  Variables keyed by string or int keys
     */
    public function validateVariables(array $variables): bool
    {
        if ($this->variable_count === 0) {
            return true;
        }

        return count($variables) >= (int) $this->variable_count;
    }

    /**
     * @return Collection<int, static>
     */
    public static function getDefaults(): Collection
    {
        // Added withoutGlobalScopes() to get templates where tenant id is null.
        /** @var Collection<int, static> */
        return self::query()->withoutGlobalScopes()->whereNull('gym_id')->isDefault()->get();
    }

    public static function isExistsForGym(int $gymId): bool
    {
        return self::query()
            ->where(function ($query) use ($gymId) {
                $query->where('gym_id', $gymId);

                if (config('services.twilio.use_individual_waba')) {
                    $query->orWhereNull('gym_id');
                }
            })
            ->isActive()
            ->exists();
    }
}
