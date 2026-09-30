<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use Database\Factories\BiometricTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int $gym_member_id
 * @property int $gym_id
 * @property int|null $enrolled_by_device_id
 * @property BiometricTypeEnum $biometric_type
 * @property string $provider_type
 * @property string|null $template_ref
 * @property float|null $quality_score
 * @property bool $is_active
 * @property BiometricTemplateSyncStatusEnum $sync_status
 * @property Carbon $enrolled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BiometricDevice|null $enrolledByDevice
 * @property-read Gym|null $gym
 * @property-read GymMember|null $gymMember
 *
 * @method static Builder<static>|BiometricTemplate active()
 * @method static BiometricTemplateFactory factory($count = null, $state = [])
 * @method static Builder<static>|BiometricTemplate forGym(int $gymId)
 * @method static Builder<static>|BiometricTemplate forGymMember(int $id)
 * @method static Builder<static>|BiometricTemplate newModelQuery()
 * @method static Builder<static>|BiometricTemplate newQuery()
 * @method static Builder<static>|BiometricTemplate ofType(string $type)
 * @method static Builder<static>|BiometricTemplate query()
 * @method static Builder<static>|BiometricTemplate whereBiometricType($value)
 * @method static Builder<static>|BiometricTemplate whereCreatedAt($value)
 * @method static Builder<static>|BiometricTemplate whereEnrolledAt($value)
 * @method static Builder<static>|BiometricTemplate whereEnrolledByDeviceId($value)
 * @method static Builder<static>|BiometricTemplate whereGymId($value)
 * @method static Builder<static>|BiometricTemplate whereGymMemberId($value)
 * @method static Builder<static>|BiometricTemplate whereId($value)
 * @method static Builder<static>|BiometricTemplate whereIsActive($value)
 * @method static Builder<static>|BiometricTemplate whereProviderType($value)
 * @method static Builder<static>|BiometricTemplate whereQualityScore($value)
 * @method static Builder<static>|BiometricTemplate whereSyncStatus($value)
 * @method static Builder<static>|BiometricTemplate whereTemplateRef($value)
 * @method static Builder<static>|BiometricTemplate whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class BiometricTemplate extends Model
{
    /** @use HasFactory<BiometricTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'gym_member_id',
        'gym_id',
        'enrolled_by_device_id',
        'biometric_type',
        'provider_type',
        'template_ref',
        'quality_score',
        'is_active',
        'sync_status',
        'enrolled_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array{
     *     template_ref: 'encrypted',
     *     quality_score: 'float',
     *     is_active: 'boolean',
     *     sync_status: 'App\Enums\BiometricTemplateSyncStatusEnum',
     *     enrolled_at: 'datetime',
     *     biometric_type: 'App\Enums\BiometricTypeEnum',
     * }
     */
    protected function casts(): array
    {
        return [
            'template_ref' => 'encrypted',
            'quality_score' => 'float',
            'is_active' => 'boolean',
            'sync_status' => BiometricTemplateSyncStatusEnum::class,
            'enrolled_at' => 'datetime',
            'biometric_type' => BiometricTypeEnum::class,
        ];
    }

    /**
     * @return BelongsTo<GymMember, $this>
     */
    public function gymMember(): BelongsTo
    {
        return $this->belongsTo(GymMember::class, 'gym_member_id');
    }

    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * @return BelongsTo<BiometricDevice, $this>
     */
    public function enrolledByDevice(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'enrolled_by_device_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forGymMember(Builder $query, int $id): void
    {
        $query->where('gym_member_id', $id);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ofType(Builder $query, string $type): void
    {
        $query->where('biometric_type', $type);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forGym(Builder $query, int $gymId): void
    {
        $query->where('gym_id', $gymId);
    }

    /**
     * Extract all searchable references (raw string and external_id if JSON).
     *
     * @return array<int, string>
     */
    public function getCacheableReferences(?string $templateRef = null): array
    {
        $ref = $templateRef ?? (string) $this->template_ref;
        if (blank($ref)) {
            return [];
        }

        $refs = [$ref];

        if (str_starts_with($ref, '{') && str_ends_with($ref, '}')) {
            $decoded = json_decode($ref, true);
            if (is_array($decoded) && isset($decoded['external_id']) && (is_string($decoded['external_id']) || is_numeric($decoded['external_id']))) {
                $refs[] = (string) $decoded['external_id'];
            }
        }

        return array_values(array_unique($refs));
    }

    /**
     * Determine if this template is managed by a third-party cloud provider.
     */
    public function hasCloudProvider(): bool
    {
        return ! in_array($this->provider_type, ['device_native', 'adms', 'hikvision', null], true);
    }

    /**
     * Resolve the hardware command PIN associated with this template.
     */
    public function resolvedCommandPin(): string
    {
        return self::resolveCommandPin($this->template_ref, $this->gym_member_id);
    }

    /**
     * Helper to resolve the hardware command PIN from a template ref and member ID.
     */
    public static function resolveCommandPin(mixed $templateRef, int|string $gymMemberId): string
    {
        $rawRef = is_scalar($templateRef) ? (string) $templateRef : '';

        return (filled($rawRef) && ! str_starts_with($rawRef, '{'))
            ? $rawRef
            : (string) $gymMemberId;
    }

    /**
     * Clear all cached template references for current and original attributes.
     */
    public function clearTemplateRefCache(): void
    {
        $gymIds = array_filter([
            $this->getOriginal('gym_id'),
            $this->gym_id,
        ], fn (mixed $id): bool => is_numeric($id) && (int) $id > 0);

        $origRef = $this->getOriginal('template_ref');

        $allRefs = array_merge(
            $this->getCacheableReferences(is_scalar($origRef) ? (string) $origRef : null),
            $this->getCacheableReferences($this->template_ref),
        );

        foreach ($gymIds as $gymId) {
            foreach ($allRefs as $ref) {
                if (filled($ref)) {
                    Cache::forget("biometric:template_ref:{$gymId}:" . hash('sha256', $ref));
                }
            }
        }
    }

    protected static function booted(): void
    {
        static::saved(function (self $template) {
            $template->clearTemplateRefCache();
        });

        static::deleted(function (self $template) {
            $template->clearTemplateRefCache();
        });
    }

    /**
     * Resolve active template by hardware/external reference for a gym.
     * Decrypts in memory since template_ref is non-deterministically encrypted.
     */
    public static function findByTemplateRef(int $gymId, string $ref): ?self
    {
        if (blank($ref)) {
            return null;
        }

        $cacheKey = "biometric:template_ref:{$gymId}:" . hash('sha256', $ref);
        $cachedId = Cache::get($cacheKey);

        if ($cachedId === 0) {
            return null;
        }

        if ($cachedId !== null) {
            /** @var self|null $cached */
            $cached = self::query()->with('gymMember')->find($cachedId);
            if ($cached && $cached->is_active && $cached->gym_id === $gymId && in_array($ref, $cached->getCacheableReferences(), true)) {
                return $cached;
            }

            Cache::forget($cacheKey);
        }

        $templates = self::query()
            ->where('gym_id', $gymId)
            ->where('is_active', true)
            ->with('gymMember')
            ->get();

        foreach ($templates as $template) {
            if (in_array($ref, $template->getCacheableReferences(), true)) {
                Cache::put($cacheKey, $template->id, 3600);

                return $template;
            }
        }

        Cache::put($cacheKey, 0, 60);

        return null;
    }
}
