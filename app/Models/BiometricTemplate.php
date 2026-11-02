<?php

declare(strict_types=1);

namespace App\Models;

use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricProvenanceEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Traits\AssertsTransactionalAuditSink;
use Database\Factories\BiometricTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
    use AssertsTransactionalAuditSink;

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
     * A terminal acknowledgement (`DATA DELETE` for the PIN) only proves anything
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
     * Template states that deleting their terminal must re-examine.
     *
     * `DeletionUnresolvable` is included even though it is not a live-or-pending state:
     * a row can be parked there without carrying the obligation flag, and the delete
     * hook is the one place that attaches the flag it is missing. The modal must count
     * exactly what the hook will park, so both read this one scope.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function parkableByDeviceDeletion(Builder $query): void
    {
        $query
            ->whereIn('sync_status', [
                ...BiometricTemplateSyncStatusEnum::liveOrPendingCredentialStates(),
                BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            ])
            // A row already parked at the obligation state has nothing new to record:
            // re-parking it on every terminal deletion appended a duplicate "parked"
            // audit entry. Its foreign key still nulls, which is what makes it
            // dischargeable.
            ->whereNot(function (Builder $query): void {
                $query->where('is_erasure_obligation', true)
                    ->where('sync_status', BiometricTemplateSyncStatusEnum::DeletionUnresolvable);
            })
            ->hardwareBacked();
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
     * the reconciler dead-letters it and no automated path remains. A row that only timed out
     * while the terminal was offline is deliberately excluded: that is a transient outage and
     * the reconciler re-drives it with a fresh budget. Unlike a cloud row, this
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
            ->deadLettered()
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
            ->deadLettered()
            ->get(['device_id', 'command_payload']);

        $keys = [];

        foreach ($commands as $command) {
            /** @var array<string, mixed> $payload */
            $payload = is_array($command->command_payload) ? $command->command_payload : [];
            $pin = $payload['Pin'] ?? null;

            if (! is_string($pin) && ! is_numeric($pin)) {
                continue;
            }

            // A ledger row whose device was deleted has no terminal to key against;
            // it cannot be the exhausted delete for a template still bound to one.
            $deviceId = $command->device_id;

            if ($deviceId === null) {
                continue;
            }

            $keys[self::hardwareDeleteCommandKey($deviceId, (string) $pin)] = true;
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
     * Three causes converge here and all need a human: a terminal `DeletionUnresolvable`
     * row (no provider reference, or a provider that outlasted the cooldown), a hardware
     * row whose `user_delete` answered and rejected `maxAttempts()` times, and any row
     * carrying the durable `is_erasure_obligation` flag — including one a later state
     * transition has converged. The relation manager's "Needs Operator" filter is defined
     * by this predicate so the causes cannot drift apart.
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
        // A durable obligation flag is the strongest signal there is: the row records an
        // erasure that has not been discharged, whatever state the rest of the lifecycle
        // has converged it to. Surfacing it is fail-safe; hiding it is how an obligation
        // survives with no operator able to see it.
        if ($this->is_erasure_obligation) {
            return true;
        }

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
    public function getCacheableReferences(): array
    {
        $ref = (string) $this->template_ref;
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
     * Whether an operator may attest that this credential was physically erased.
     *
     * The action writes `Deleted` and clears `is_erasure_obligation`, which is a claim that
     * the credential no longer exists. That claim is only honest when no automated path can
     * still reach it:
     *
     * - a cloud-backed row is erased through its provider API — a human confirmation would
     *   attest an erase the provider never performed;
     * - a row still bound to a terminal can still be commanded to delete it, so the
     *   automated path (or deleting the terminal, which carries the credential into the
     *   tombstone) is the correct exit;
     * - an already-`Deleted` row has nothing left to discharge.
     */
    public function canBeDischargedByOperator(): bool
    {
        return $this->is_erasure_obligation
            && ! $this->hasCloudProvider()
            && $this->enrolled_by_device_id === null
            && $this->sync_status !== BiometricTemplateSyncStatusEnum::Deleted;
    }

    /**
     * Park this credential as an erasure obligation no automated path can discharge.
     *
     * This is the single implementation of the invariant "the terminal state and the
     * obligation flag are written together". It used to be copied into the delete hook,
     * three de-provisioner arms and the reconciler, and the copies drifted: one arm could
     * write `DeletionUnresolvable` without the flag and the operator surface then could not
     * see it. Every producer must call this method.
     *
     * The entry is audited here as well as logged, because the log is rotation-bound and an
     * auditor reconstructing "why was this credential never erased" needs the reason and
     * the command/return code in the activity log.
     *
     * @param  array<string, mixed>  $context
     */
    public function parkAsUnresolvableObligation(string $reason, array $context = []): void
    {
        $this->assertAuditSinkIsTransactional();

        DB::transaction(function () use ($reason, $context): void {
            $fromStatus = $this->sync_status;

            $this->forceFill([
                'sync_status' => BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
                'is_active' => false,
                'is_erasure_obligation' => true,
            ])->save();

            $logged = activity('biometric')
                ->performedOn($this)
                ->withProperties([
                    'template_id' => $this->id,
                    'gym_id' => $this->gym_id,
                    'member_id' => $this->gym_member_id,
                    'device_id' => $this->enrolled_by_device_id,
                    'from_status' => $fromStatus?->value,
                    'to_status' => BiometricTemplateSyncStatusEnum::DeletionUnresolvable->value,
                    'reason' => $reason,
                ] + $context)
                ->log('Biometric erasure obligation parked for operator');

            if ($logged === null) {
                throw new RuntimeException(
                    'The erasure obligation was not parked because its audit entry could not be written.'
                );
            }
        });
    }

    /**
     * Discharge the obligation if the row still carries it.
     *
     * The flag is the precondition, so a second click (or a concurrent discharge) affects
     * zero rows and writes no second audit entry. Extracted so the panel action and a
     * direct test exercise the same compare-and-set rather than two copies.
     */
    public function dischargeIfFlagged(?User $actor = null, string $description = 'Biometric erasure confirmed by operator after physical wipe'): bool
    {
        $this->assertAuditSinkIsTransactional();

        $fromStatus = $this->sync_status;

        return DB::transaction(function () use ($actor, $description, $fromStatus): bool {
            $affected = self::query()
                ->whereKey($this->getKey())
                ->where('is_erasure_obligation', true)
                ->update([
                    'sync_status' => BiometricTemplateSyncStatusEnum::Deleted,
                    'is_active' => false,
                    'is_erasure_obligation' => false,
                ]);

            if ($affected !== 1) {
                return false;
            }

            $activityLogger = activity('biometric')
                ->performedOn($this)
                ->withProperties([
                    'template_id' => $this->id,
                    'actor_id' => $actor?->getKey(),
                    'from_status' => $fromStatus?->value,
                    'to_status' => BiometricTemplateSyncStatusEnum::Deleted->value,
                    'confirmed_at' => now()->toIso8601String(),
                ]);

            if ($actor !== null) {
                $activityLogger->causedBy($actor);
            }

            if ($activityLogger->log($description) === null) {
                throw new RuntimeException(
                    'The erasure was not recorded because its audit entry could not be written.'
                );
            }

            $this->refresh();
            $this->clearTemplateRefCache();

            return true;
        });
    }

    /**
     * Whether an operator may attest that a cloud credential with no reference is gone.
     *
     * The provider may hold a credential the API can no longer address. There is no
     * automated erase and no reference to repair, so the only honest exit is a human
     * attestation scoped to this row, with the same fail-closed audit requirement as the
     * physical-wipe discharge.
     */
    public function canAttestCloudErasure(): bool
    {
        return $this->is_erasure_obligation
            && $this->hasCloudProvider()
            && blank($this->template_ref)
            && $this->sync_status !== BiometricTemplateSyncStatusEnum::Deleted;
    }

    /**
     * Converge this row to `Deleted` and clear the obligation flag.
     *
     * Every route that actually erases the credential — a provider confirmation, a terminal
     * ACK, a proven non-delivery or an operator's physical-wipe attestation — must call this.
     * Leaving the flag set made a correctly erased row surface forever with no action that
     * could clear it, and blocked a later enrolment from superseding a dead obligation.
     */
    public function markErasureDischarged(): void
    {
        $this->forceFill([
            'sync_status' => BiometricTemplateSyncStatusEnum::Deleted,
            'is_active' => false,
            'is_erasure_obligation' => false,
        ])->save();
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
     * that placeholder as delivered and queue a hardware `DATA DELETE`, erasing the credential
     * the earlier enrolment actually put on the terminal.
     *
     * `SENT` counts as delivered. `markSent()` is reached when the terminal dequeues the
     * command, so a `SENT` `user_add` may already have been applied even though no
     * acknowledgement was recorded — treating it as undelivered would write off a live
     * credential as `Deleted` with no erase queued. Only `PENDING` proves non-delivery.
     *
     * When `enrolled_by_device_id` is null the ledger is still consulted, because the foreign
     * key is not the only durable record of delivery: every canonical `user_add` key ends with
     * the template id it enrolled, and the row's `gym_id` scopes the lookup. Without this the
     * sweep and the de-provisioner lose the evidence the moment a row is detached without
     * passing the tombstone hook (a restored dump, or a writer that nulls the FK directly),
     * and converge a credential the terminal is holding to `Deleted`.
     */
    public function wasDeliveredToHardware(): bool
    {
        // Durable check: hardware_confirmed_at is set when the device ACKs user_add
        if ($this->hardware_confirmed_at !== null) {
            return true;
        }

        $delivered = AdmsDeviceCommand::query()
            ->where('gym_id', $this->gym_id)
            ->where('command_type', 'user_add')
            ->whereIn('status', [
                AdmsCommandStatusEnum::SENT,
                AdmsCommandStatusEnum::ACKNOWLEDGED,
            ]);

        if ($this->enrolled_by_device_id !== null) {
            return $delivered
                ->where('device_id', $this->enrolled_by_device_id)
                ->whereIn('idempotency_key', $this->ownUserAddIdempotencyKeys())
                ->exists();
        }

        $templateKey = $this->getKey();

        if (! is_numeric($templateKey)) {
            return false;
        }

        return $delivered
            ->where('idempotency_key', 'like', '%:' . $templateKey)
            ->exists();
    }

    /**
     * The single evidence rule for "the hardware may still hold this credential".
     *
     * Both halves are deliberately read together: a row can be parked for what it *is*
     * (sync state, obligation, acknowledgement) or for what the ledger *proves*
     * (a `SENT`/`ACKNOWLEDGED` `user_add`). Splitting them at a call site is how the
     * tombstone came to park a row the sweep then hard-deleted.
     */
    public function hasDurableHardwareEvidence(): bool
    {
        return $this->mayBeHeldByHardware() || $this->wasDeliveredToHardware();
    }

    /**
     * Whether the terminal may still be holding this credential.
     *
     * This is the single evidence rule for "the hardware may still hold it": an
     * explicit erasure obligation, a recorded hardware acknowledgement, or a state
     * the terminal produces or is asked to produce (`Synced`, `Propagated`,
     * `PendingDeletion`, and `DeletionUnresolvable`). A `Pending` placeholder with
     * none of that evidence provably never reached flash and may be written off.
     *
     * `DeletionUnresolvable` is included deliberately: a row can be parked in that
     * state without carrying the obligation flag, so the absence of the flag is not
     * proof that an operator discharged the obligation.
     */
    public function mayBeHeldByHardware(): bool
    {
        if ($this->is_erasure_obligation || $this->hardware_confirmed_at !== null) {
            return true;
        }

        return in_array($this->sync_status, [
            BiometricTemplateSyncStatusEnum::Synced,
            BiometricTemplateSyncStatusEnum::Propagated,
            BiometricTemplateSyncStatusEnum::PendingDeletion,
            BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
        ], true);
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
     * Invalidate every cached template reference for this row's gym.
     *
     * Invalidation is a per-gym generation bump, not a delete of each reference key. A key
     * now carries the terminal scope, so one reference has as many keys as there are
     * terminals that resolve it, and the old "forget this ref" loop could only guess at
     * them. Bumping the generation makes every previous key unreachable in one write; the
     * entries themselves expire on their own TTL.
     *
     * The generation key is seeded before it is incremented. `Cache::increment()` is a
     * no-op on a missing key for the database store (and for any store whose increment is
     * implemented as a conditional UPDATE), so without the seed every invalidation did
     * nothing on that store and stale attributions survived for the TTL. Production uses
     * Redis, where increment creates the key, but the store must not decide correctness.
     */
    public function clearTemplateRefCache(): void
    {
        $gymIds = array_values(array_unique(array_filter([
            $this->getOriginal('gym_id'),
            $this->gym_id,
        ], fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)));

        foreach ($gymIds as $gymId) {
            $key = self::templateRefCacheGenerationKey((int) $gymId);

            // `add()` is atomic: it seeds the key only when absent, so concurrent
            // invalidators cannot reset a generation another writer just bumped.
            Cache::add($key, 1);
            Cache::increment($key);
        }
    }

    /**
     * The cache key a scoped (or unscoped) reference lookup reads and writes.
     *
     * The scope is part of the key. Keying only on gym + reference made two terminals that
     * share a PIN fight over one entry: terminal B's out-of-scope hit forgot A's key, A
     * re-queried and overwrote B's, and every alternating punch paid a full active-template
     * scan with in-memory decryption of the encrypted reference column.
     */
    public static function templateRefCacheKey(int $gymId, string $ref, ?int $deviceId = null): string
    {
        $generation = Cache::get(self::templateRefCacheGenerationKey($gymId));
        $generation = is_numeric($generation) ? (int) $generation : 0;
        $scope = $deviceId === null ? 'any' : (string) $deviceId;

        return "biometric:template_ref:{$gymId}:{$generation}:{$scope}:" . hash('sha256', $ref);
    }

    /**
     * The per-gym generation counter that scopes every reference-cache key.
     */
    public static function templateRefCacheGenerationKey(int $gymId): string
    {
        return "biometric:template_ref:generation:{$gymId}";
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
     *
     * `$deviceId` restricts the lookup to the requested terminal's provenance: a
     * row enrolled on that device, or a cloud-backed row with no device (which
     * the provider, not a terminal, holds). Without it a punch could resolve a
     * template enrolled on an *unrelated* terminal in the same gym — the fallback
     * used to scan every active row in the gym and was only safe while the
     * deletion tombstone happened to deactivate orphans.
     */
    public static function findByTemplateRef(int $gymId, string $ref, ?int $deviceId = null): ?self
    {
        if (blank($ref)) {
            return null;
        }

        $cacheKey = self::templateRefCacheKey($gymId, $ref, $deviceId);
        $cachedId = Cache::get($cacheKey);

        if ($cachedId === 0) {
            return null;
        }

        if ($cachedId !== null) {
            /** @var self|null $cached */
            $cached = self::query()->with('gymMember')->find($cachedId);
            if ($cached
                && $cached->is_active
                && $cached->gym_id === $gymId
                && $cached->belongsToTerminalScope($deviceId)
                && in_array($ref, $cached->getCacheableReferences(), true)) {
                return $cached;
            }

            Cache::forget($cacheKey);
        }

        $templates = self::query()
            ->where('gym_id', $gymId)
            ->where('is_active', true)
            ->when($deviceId !== null, function (Builder $query) use ($deviceId): void {
                $query->where(function (Builder $scope) use ($deviceId): void {
                    $scope->where('enrolled_by_device_id', $deviceId)
                        ->orWhere(function (Builder $unbound): void {
                            $unbound->whereNull('enrolled_by_device_id')->cloudBacked();
                        });
                });
            })
            ->with('gymMember')
            ->get();

        foreach ($templates as $template) {
            if (in_array($ref, $template->getCacheableReferences(), true)) {
                Cache::put($cacheKey, $template->id, 3600);

                return $template;
            }
        }

        // A negative result from one terminal must not poison another terminal's
        // lookup for the same reference, so only an unscoped miss is cached.
        if ($deviceId === null) {
            Cache::put($cacheKey, 0, 60);
        }

        return null;
    }

    /**
     * Whether this row can be attributed to the given terminal.
     *
     * A null `$deviceId` means "no scope" (legacy callers). A scoped lookup only
     * accepts a row enrolled on that device, or a cloud-backed row the provider
     * (not a terminal) holds. An orphaned hardware row may be in a terminal's
     * flash somewhere, but this punch did not come from it.
     */
    private function belongsToTerminalScope(?int $deviceId): bool
    {
        if ($deviceId === null) {
            return true;
        }

        if ($this->enrolled_by_device_id === $deviceId) {
            return true;
        }

        return $this->enrolled_by_device_id === null && $this->hasCloudProvider();
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
