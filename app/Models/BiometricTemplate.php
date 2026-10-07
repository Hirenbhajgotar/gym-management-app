<?php

declare(strict_types=1);

namespace App\Models;

use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use Database\Factories\BiometricTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
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
 * @property BiometricTemplateSyncStatusEnum|null $sync_status
 * @property string|null $superseded_reason
 * @property Carbon|null $enrolled_at
 * @property Carbon|null $hardware_confirmed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
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
 * @method static Builder<static>|BiometricTemplate supersedableForNewEnrolment(int $gymId, int $memberId, int $deviceId, string $biometricType, ?int $exceptTemplateId = null)
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

    use SoftDeletes;

    /**
     * Written when a new enrolment replaces this template.
     */
    public const SUPERSEDED_BY_RE_ENROLMENT = 're_enrolment';

    /**
     * Written when a GDPR Art. 17 erasure request is overtaken by a re-enrolment.
     *
     * Retained as a distinct value because it carries a compliance obligation the other
     * reason does not: an operator has overridden a member's erasure request, and that
     * must remain queryable and reportable to a regulator.
     */
    public const SUPERSEDED_BY_ERASURE_OVERRIDE = 'erasure_override';

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
        'superseded_reason',
        'enrolled_at',
        'hardware_confirmed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array{
     *     template_ref: 'encrypted',
     *     quality_score: 'float',
     *     is_active: 'boolean',
     *     sync_status: 'App\Enums\BiometricTemplateSyncStatusEnum',
     *     superseded_reason: 'string',
     *     enrolled_at: 'datetime',
     *     hardware_confirmed_at: 'datetime',
     *     biometric_type: 'App\Enums\BiometricTypeEnum',
     *     deleted_at: 'datetime',
     * }
     */
    protected function casts(): array
    {
        return [
            'template_ref' => 'encrypted',
            'quality_score' => 'float',
            'is_active' => 'boolean',
            'sync_status' => BiometricTemplateSyncStatusEnum::class,
            'superseded_reason' => 'string',
            'enrolled_at' => 'datetime',
            'hardware_confirmed_at' => 'datetime',
            'biometric_type' => BiometricTypeEnum::class,
            'deleted_at' => 'datetime',
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
     * Find templates supersedable when a new enrolment occurs for a member, device and modality.
     *
     * `$biometricType` is mandatory on purpose. Erasure is queued per hardware PIN, and a
     * member's PIN is shared across modalities on one device, so a whole-member scope would
     * silently cancel a pending `user_delete` for a modality the operator is not re-enrolling.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function supersedableForNewEnrolment(
        Builder $query,
        int $gymId,
        int $memberId,
        int $deviceId,
        string $biometricType,
        ?int $exceptTemplateId = null
    ): void {
        $query
            ->where('gym_id', $gymId)
            ->where('gym_member_id', $memberId)
            ->where('enrolled_by_device_id', $deviceId)
            ->where('biometric_type', $biometricType)
            ->when($exceptTemplateId !== null, fn (Builder $q) => $q->where('id', '!=', $exceptTemplateId))
            ->whereIn('sync_status', [
                BiometricTemplateSyncStatusEnum::Pending,
                BiometricTemplateSyncStatusEnum::PendingDeletion,
                BiometricTemplateSyncStatusEnum::Synced,
                BiometricTemplateSyncStatusEnum::Propagated,
            ]);
    }

    /**
     * True when another active template for this member shares the device-bound PIN.
     *
     * The ADMS protocol can only erase a whole PIN, so while such a sibling exists the
     * hardware credential must be retained and the erasure request parked instead.
     */
    public function hasActiveSiblingOnSameDevice(): bool
    {
        if ($this->enrolled_by_device_id === null) {
            return false;
        }

        return self::query()
            ->where('gym_member_id', $this->gym_member_id)
            ->where('enrolled_by_device_id', $this->enrolled_by_device_id)
            ->where('is_active', true)
            ->whereIn('sync_status', [
                BiometricTemplateSyncStatusEnum::Synced,
                BiometricTemplateSyncStatusEnum::Propagated,
            ])
            ->where('id', '!=', $this->id)
            ->exists();
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
     * Check if *this template's* user_add command was acknowledged by hardware.
     *
     * The command log is consulted only for rows whose `hardware_confirmed_at` was never
     * written, and the lookup is scoped to this template's own idempotency key. That scope is
     * load-bearing: a member's command PIN is their member id and is stable across
     * re-enrolments, so a PIN-scoped lookup would attribute an older enrolment's acknowledgement
     * to a brand-new `Pending` placeholder. The abandoned-placeholder sweep would then treat
     * that placeholder as delivered and queue `DATA DELETE user Pin=N`, erasing the credential
     * the earlier enrolment actually put on the terminal.
     */
    public function wasDeliveredToHardware(): bool
    {
        if (! $this->enrolled_by_device_id) {
            return false;
        }

        // Durable check: hardware_confirmed_at is set when the device ACKs user_add
        if ($this->hardware_confirmed_at !== null) {
            return true;
        }

        return AdmsDeviceCommand::query()
            ->where('device_id', $this->enrolled_by_device_id)
            ->where('command_type', 'user_add')
            ->whereIn('idempotency_key', $this->ownUserAddIdempotencyKeys())
            ->where('status', AdmsCommandStatusEnum::ACKNOWLEDGED)
            ->exists();
    }

    /**
     * Every canonical `user_add` key this template's own enrolment can produce.
     *
     * Enrolment initiation and the nightly reconciler are the only two producers, and both
     * embed this template's id in the key.
     *
     * @return array<int, string>
     */
    protected function ownUserAddIdempotencyKeys(): array
    {
        if ($this->enrolled_by_device_id === null) {
            return [];
        }

        return [
            AdmsCommandKey::buildUserAdd(
                $this->gym_id,
                $this->enrolled_by_device_id,
                $this->gym_member_id,
                $this->id
            ),
            AdmsCommandKey::buildUserAdd(
                $this->gym_id,
                $this->enrolled_by_device_id,
                $this->gym_member_id,
                $this->id,
                AdmsCommandKey::STAGE_RECONCILE
            ),
        ];
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

    /**
     * Get human-readable badge label reflecting hardware delivery state for Propagated templates.
     */
    public function getSyncStatusBadgeLabel(): string
    {
        if ($this->sync_status === BiometricTemplateSyncStatusEnum::Propagated) {
            return ($this->hardware_confirmed_at !== null || $this->enrolled_at !== null)
                ? 'Propagated (Confirmed)'
                : 'Propagated (Awaiting Device)';
        }

        return $this->sync_status?->getLabel() ?? 'Unknown';
    }

    /**
     * Get badge color reflecting hardware delivery state for Propagated templates.
     */
    public function getSyncStatusBadgeColor(): string
    {
        if ($this->sync_status === BiometricTemplateSyncStatusEnum::Propagated) {
            return ($this->hardware_confirmed_at !== null || $this->enrolled_at !== null)
                ? 'success'
                : 'warning';
        }

        return $this->sync_status?->getColor() ?? 'gray';
    }
}
