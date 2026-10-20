<?php

declare(strict_types=1);

namespace App\Models;

use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricProvenanceEnum;
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
 * @property bool $is_erasure_obligation
 * @property int $erasure_attempts
 * @property Carbon|null $erasure_first_attempted_at
 * @property Carbon|null $erasure_requested_at
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
 * @method static Builder<static>|BiometricTemplate cloudBacked()
 * @method static Builder<static>|BiometricTemplate hardwareBacked()
 * @method static Builder<static>|BiometricTemplate ofProvenance(BiometricProvenanceEnum $provenance)
 * @method static Builder<static>|BiometricTemplate ofType(string $type)
 * @method static Builder<static>|BiometricTemplate supersedableForNewEnrolment(int $gymId, int $memberId, int $deviceId, string $biometricType, BiometricProvenanceEnum $provenance, ?int $exceptTemplateId = null)
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
     * Written when an explicit erasure request — member- or operator-initiated — is overtaken
     * by a re-enrolment.
     *
     * The request is any explicit erasure request written by
     * {@see \App\Biometrics\Services\BiometricDataDeletionService::deleteSingleTemplate()},
     * the single chokepoint — deliberately wider than "the member filed an Art. 17 request".
     * Retained as a distinct value because it carries a compliance obligation the other
     * reason does not: an explicit erasure request has been overridden, and that must remain
     * queryable and reportable to a regulator.
     */
    public const SUPERSEDED_BY_ERASURE_OVERRIDE = 'erasure_override';

    /**
     * Provider types whose credential lives on the ADMS terminal itself.
     *
     * The list is a single source of truth so the model predicate, the hardware-only
     * query scope and the terminal ACK convergence agree about which rows the hardware
     * owns. A `null` `provider_type` is treated as hardware by both
     * {@see self::hasCloudProvider()} and {@see self::hardwareBacked()}; anything else
     * that is not listed here is provider/cloud-backed.
     *
     * @var array<int, string>
     */
    public const HARDWARE_PROVIDER_TYPES = ['device_native', 'adms', 'hikvision'];

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
     * `is_erasure_obligation` and `erasure_requested_at` are deliberately absent from
     * $fillable: they are compliance discriminators, written explicitly by the one
     * service that owns each fact rather than mass-assigned.
     *
     * Both halves of the per-row erasure budget — `erasure_attempts` and
     * `erasure_first_attempted_at` — are deliberately not mass-assignable for the same
     * reason. The counter is written only through `increment()` / `forceFill()`, so a
     * caller cannot reset or fabricate the retry budget by filling an attribute.
     *
     * @return array{
     *     template_ref: 'encrypted',
     *     quality_score: 'float',
     *     is_active: 'boolean',
     *     is_erasure_obligation: 'boolean',
     *     erasure_attempts: 'integer',
     *     erasure_first_attempted_at: 'datetime',
     *     erasure_requested_at: 'datetime',
     *     provider_type: 'string',
     *     sync_status: 'App\Enums\BiometricTemplateSyncStatusEnum',
     *     superseded_reason: 'string',
     *     enrolled_at: 'datetime',
     *     hardware_confirmed_at: 'datetime',
     *     biometric_type: 'App\Enums\BiometricTypeEnum',
     *     deleted_at: 'datetime',
     *     gym_id: 'integer',
     * }
     */
    protected function casts(): array
    {
        return [
            'template_ref' => 'encrypted',
            'quality_score' => 'float',
            'is_active' => 'boolean',
            'is_erasure_obligation' => 'boolean',
            'erasure_attempts' => 'integer',
            'erasure_first_attempted_at' => 'datetime',
            'erasure_requested_at' => 'datetime',
            'provider_type' => 'string',
            'sync_status' => BiometricTemplateSyncStatusEnum::class,
            'superseded_reason' => 'string',
            'enrolled_at' => 'datetime',
            'hardware_confirmed_at' => 'datetime',
            'biometric_type' => BiometricTypeEnum::class,
            'deleted_at' => 'datetime',
            'gym_id' => 'integer',
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
     * Restrict to templates whose credential lives on the ADMS terminal itself.
     *
     * A terminal acknowledgement (`DATA DELETE user Pin=N`) only proves anything
     * about credentials the terminal holds, so every convergence driven by an ADMS
     * ACK must be scoped to hardware-backed rows. A cloud/provider-backed row on the
     * same device shares the PIN but not the credential, and must never be
     * converged by a hardware ACK.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function hardwareBacked(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->whereIn('provider_type', self::HARDWARE_PROVIDER_TYPES)
                ->orWhereNull('provider_type');
        });
    }

    /**
     * Restrict to templates whose credential lives at a cloud provider.
     *
     * The exact complement of {@see self::hardwareBacked()}, so a writer that is about to
     * change a row's provenance can require the row to already belong to the same class. A
     * hardware and a cloud credential on one PIN are two different credentials; rewriting
     * one as the other destroys the reference the abandoned credential lives by.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function cloudBacked(Builder $query): void
    {
        $query->whereNotNull('provider_type')
            ->whereNotIn('provider_type', self::HARDWARE_PROVIDER_TYPES);
    }

    /**
     * Restrict to rows whose credential lives on the given side of a dual-mode device.
     *
     * `hardwareBacked()` and `cloudBacked()` are exact complements, so this is the named form
     * of the same predicate: a writer that is about to demote rows passes the provenance of
     * the enrolment driving it and can never match the other class.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ofProvenance(Builder $query, BiometricProvenanceEnum $provenance): void
    {
        $provenance === BiometricProvenanceEnum::Hardware
            ? $query->hardwareBacked()
            : $query->cloudBacked();
    }

    /**
     * Find templates supersedable when a new enrolment occurs for a member, device and modality.
     *
     * `$biometricType` is mandatory on purpose. Erasure is queued per hardware PIN, and a
     * member's PIN is shared across modalities on one device, so a whole-member scope would
     * silently cancel a pending `user_delete` for a modality the operator is not re-enrolling.
     *
     * Rows flagged `is_erasure_obligation` are excluded. They are machine-written orphan
     * markers, not templates a new enrolment replaces: the credential they record lives at
     * the provider, which a device-side re-enrolment does not touch. Superseding one would
     * cancel the erasure obligation and write a false "GDPR erasure overridden" audit line for
     * an erasure request that was never made.
     *
     * A supersede only ever demotes a row of the same provenance class as the enrolment
     * driving it. On ADR-004's dual-mode device a hardware and a cloud credential share a PIN
     * but are two credentials, and `Superseded` is excluded from every live-credential
     * predicate and the erasure reconciler, so demoting across classes strands a live
     * credential: the provider reference or terminal profile survives while no path revisits
     * it.
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
        BiometricProvenanceEnum $provenance,
        ?int $exceptTemplateId = null
    ): void {
        $query
            ->where('gym_id', $gymId)
            ->where('gym_member_id', $memberId)
            ->where('enrolled_by_device_id', $deviceId)
            ->where('biometric_type', $biometricType)
            ->where('is_erasure_obligation', false)
            ->ofProvenance($provenance)
            ->when($exceptTemplateId !== null, fn (Builder $q) => $q->where('id', '!=', $exceptTemplateId))
            ->whereIn('sync_status', BiometricTemplateSyncStatusEnum::liveOrPendingCredentialStates());
    }

    /**
     * True when another active template for this member shares the device-bound PIN.
     *
     * The ADMS protocol can only erase a whole PIN, so while such a sibling exists the
     * hardware credential must be retained and the erasure request parked instead.
     *
     * The sibling must itself be hardware-backed: the PIN is shared with a cloud row on the
     * same dual-mode device, but the terminal does not hold that credential, so erasing the
     * PIN cannot destroy it and it must not defer the hardware erasure.
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
            ->hardwareBacked()
            ->whereIn('sync_status', BiometricTemplateSyncStatusEnum::terminalCredentialStates())
            ->where('id', '!=', $this->id)
            ->exists();
    }

    /**
     * True when a `user_delete` for this row's PIN has exhausted its hardware delivery budget.
     *
     * The terminal answered and rejected the erase `AdmsCommandKey::maxAttempts()` times, so
     * the reconciler dead-letters it and no automated path remains. Unlike a cloud row, this
     * state is not written to the template, so the operator surface has to ask the command
     * log — that is the difference between an alerted failure and a silent one.
     *
     * The command log is scoped explicitly to this row's `gym_id` as well as its device:
     * device ids are not guaranteed globally unique across a restored or sharded database,
     * and a cross-tenant command must never make this template look dead-lettered.
     */
    public function hasExhaustedHardwareDelete(): bool
    {
        if ($this->enrolled_by_device_id === null) {
            return false;
        }

        return AdmsDeviceCommand::query()
            ->where('gym_id', $this->gym_id)
            ->where('device_id', $this->enrolled_by_device_id)
            ->where('command_type', 'user_delete')
            ->where('command_payload->Pin', $this->resolvedCommandPin())
            ->where('status', AdmsCommandStatusEnum::FAILED)
            ->where('attempt_count', '>=', AdmsCommandKey::maxAttempts())
            ->exists();
    }

    /**
     * The `user_delete` command log keys that have exhausted their hardware delivery cap,
     * for an entire gym, in one query.
     *
     * Each key is `"{device_id}|{pin}"`, the same key the per-row predicate looks up through
     * {@see self::hardwareDeleteCommandKey()}, so the bulk set and a single template's
     * {@see self::hasExhaustedHardwareDelete()} cannot drift. The query carries that
     * predicate's conditions exactly: gym scope, `user_delete`, `FAILED` and the attempt cap.
     *
     * A payload whose `Pin` is not a string or number contributes no key, keeping the same
     * fail-closed direction as the single-row check.
     *
     * @return array<string, true>
     */
    public static function exhaustedHardwareDeleteKeys(int $gymId): array
    {
        $commands = AdmsDeviceCommand::query()
            ->where('gym_id', $gymId)
            ->where('command_type', 'user_delete')
            ->where('status', AdmsCommandStatusEnum::FAILED)
            ->where('attempt_count', '>=', AdmsCommandKey::maxAttempts())
            ->get(['device_id', 'command_payload']);

        $keys = [];

        foreach ($commands as $command) {
            /** @var array<string, mixed> $payload */
            $payload = is_array($command->command_payload) ? $command->command_payload : [];
            $pin = $payload['Pin'] ?? null;

            if (! is_string($pin) && ! is_numeric($pin)) {
                continue;
            }

            $keys[self::hardwareDeleteCommandKey($command->device_id, (string) $pin)] = true;
        }

        return $keys;
    }

    /**
     * The one key form under which an exhausted `user_delete` is recorded and looked up.
     *
     * Both {@see self::exhaustedHardwareDeleteKeys()} and
     * {@see self::requiresOperatorAttention()} build their keys here, so a change to the form
     * cannot leave the bulk set and the per-row lookup disagreeing.
     *
     * `$deviceId` deliberately accepts `int|string`: some drivers hydrate integer columns as
     * strings, and the concatenation normalises either form to the same key.
     */
    protected static function hardwareDeleteCommandKey(int|string $deviceId, string $pin): string
    {
        return $deviceId . '|' . $pin;
    }

    /**
     * Whether this row is an erasure obligation the system cannot discharge by itself.
     *
     * Two causes converge here and both need a human: a terminal `DeletionUnresolvable`
     * row (no provider reference, or a provider that outlasted the cooldown) and a hardware
     * row whose `user_delete` answered and rejected `maxAttempts()` times. The relation
     * manager's "Needs Operator" filter is defined by this predicate so the two causes cannot
     * drift apart.
     *
     * With no `$exhaustedHardwareDeleteKeys` the cap is read from the command log for this row
     * alone ({@see self::hasExhaustedHardwareDelete()}). A bulk caller passes the precomputed
     * {@see self::exhaustedHardwareDeleteKeys()} set so an entire candidate list costs one
     * query; both modes read the same `"{device_id}|{pin}"` key built by
     * {@see self::hardwareDeleteCommandKey()}.
     *
     * @param  array<string, true>|null  $exhaustedHardwareDeleteKeys
     */
    public function requiresOperatorAttention(?array $exhaustedHardwareDeleteKeys = null): bool
    {
        if ($this->sync_status === BiometricTemplateSyncStatusEnum::DeletionUnresolvable) {
            return true;
        }

        if ($this->sync_status !== BiometricTemplateSyncStatusEnum::PendingDeletion) {
            return false;
        }

        if ($exhaustedHardwareDeleteKeys === null) {
            return $this->hasExhaustedHardwareDelete();
        }

        if ($this->enrolled_by_device_id === null) {
            return false;
        }

        return isset($exhaustedHardwareDeleteKeys[
            self::hardwareDeleteCommandKey($this->enrolled_by_device_id, $this->resolvedCommandPin())
        ]);
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
        return ! in_array($this->provider_type, [...self::HARDWARE_PROVIDER_TYPES, null], true);
    }

    /**
     * The provenance class this row's credential belongs to.
     */
    public function provenance(): BiometricProvenanceEnum
    {
        return BiometricProvenanceEnum::forProviderType($this->provider_type);
    }

    /**
     * Resolve the hardware command PIN associated with this template.
     */
    public function resolvedCommandPin(): string
    {
        return self::resolveCommandPin($this->template_ref, $this->gym_member_id);
    }

    /**
     * Check whether this template's `user_add` reached the terminal.
     *
     * The command log is consulted only for rows whose `hardware_confirmed_at` was never
     * written, and the lookup is scoped to this template's own idempotency key. That scope is
     * load-bearing: a member's command PIN is their member id and is stable across
     * re-enrolments, so a PIN-scoped lookup would attribute an older enrolment's acknowledgement
     * to a brand-new `Pending` placeholder. The abandoned-placeholder sweep would then treat
     * that placeholder as delivered and queue `DATA DELETE user Pin=N`, erasing the credential
     * the earlier enrolment actually put on the terminal.
     *
     * `SENT` counts as delivered. `markSent()` is reached when the terminal dequeues the
     * command, so a `SENT` `user_add` may already have been applied even though no
     * acknowledgement was recorded — treating it as undelivered would write off a live
     * credential as `Deleted` with no erase queued. Only `PENDING` proves non-delivery.
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
            ->whereIn('status', [
                AdmsCommandStatusEnum::SENT,
                AdmsCommandStatusEnum::ACKNOWLEDGED,
            ])
            ->exists();
    }

    /**
     * Every canonical `user_add` key this template's own enrolment can produce.
     *
     * Enrolment initiation and the daily `biometrics:reconcile-enrollments` reconciler are the
     * only two producers, and both embed this template's id in the key.
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
