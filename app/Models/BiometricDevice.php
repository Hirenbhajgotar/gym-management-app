<?php

declare(strict_types=1);

namespace App\Models;

use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Casts\ProviderConfigCast;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\AdmsUserTableEnum;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricProvidersEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Exceptions\DeviceDeletionVetoedException;
use App\Exceptions\UnreadableProviderConfigException;
use App\Observers\BiometricDeviceObserver;
use App\Traits\AssertsTransactionalAuditSink;
use Database\Factories\BiometricDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Stringable;
use Throwable;

/**
 * Biometric terminal, including the ADMS communication-key verifier.
 *
 * `provider_config['comm_key_source']` is server-derived: the saving hook sets
 * it on a cloud→native switch, and translates a device-native `comm_key` ADMS
 * alias to `pushcommkey` when the provider leaves device-native mode. {@see ProviderConfigCast}
 * strips submitted provenance from Eloquent attribute writes. Writers that bypass the cast or the
 * hook — a raw query-builder `Model::query()->update()` (including `DB::table()`
 * writes and direct ciphertext imports), or a `saveQuietly()` that changes only
 * `provider_type` without writing `provider_config` — are outside that guard.
 * Those writers must derive provenance themselves; provenance cannot be
 * treated as trusted for them.
 *
 * @property int $id
 * @property int $gym_id
 * @property string $device_name
 * @property string|null $device_serial
 * @property string $device_type
 * @property BiometricProvidersEnum|null $provider_type
 * @property array<array-key, mixed>|null $provider_config
 * @property BiometricDeviceStatusEnum $status
 * @property BiometricDevicePurpose $purpose
 * @property bool $adms_enabled
 * @property string|null $adms_comm_key_hash
 * @property string|null $adms_comm_key_salt
 * @property int|null $assigned_staff_id
 * @property Carbon|null $last_heartbeat_at
 * @property string|null $device_token
 * @property string|null $token_hash
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $assignedStaff
 * @property-read User $createdBy
 * @property-read Collection<int, DoorEvent> $doorEvents
 * @property-read int|null $door_events_count
 * @property-read Gym $gym
 * @property-read Collection<int, BiometricTemplate> $templates
 * @property-read int|null $templates_count
 *
 * @method static Builder<static>|BiometricDevice active()
 * @method static BiometricDeviceFactory factory($count = null, $state = [])
 * @method static Builder<static>|BiometricDevice forGym(int $gymId)
 * @method static Builder<static>|BiometricDevice newModelQuery()
 * @method static Builder<static>|BiometricDevice newQuery()
 * @method static Builder<static>|BiometricDevice query()
 * @method static Builder<static>|BiometricDevice whereAssignedStaffId($value)
 * @method static Builder<static>|BiometricDevice whereCreatedAt($value)
 * @method static Builder<static>|BiometricDevice whereCreatedBy($value)
 * @method static Builder<static>|BiometricDevice whereDeviceName($value)
 * @method static Builder<static>|BiometricDevice whereDeviceSerial($value)
 * @method static Builder<static>|BiometricDevice whereDeviceToken($value)
 * @method static Builder<static>|BiometricDevice whereDeviceType($value)
 * @method static Builder<static>|BiometricDevice whereGymId($value)
 * @method static Builder<static>|BiometricDevice whereId($value)
 * @method static Builder<static>|BiometricDevice whereLastHeartbeatAt($value)
 * @method static Builder<static>|BiometricDevice whereProviderConfig($value)
 * @method static Builder<static>|BiometricDevice whereProviderType($value)
 * @method static Builder<static>|BiometricDevice wherePurpose($value)
 * @method static Builder<static>|BiometricDevice whereStatus($value)
 * @method static Builder<static>|BiometricDevice whereTokenHash($value)
 * @method static Builder<static>|BiometricDevice whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy([BiometricDeviceObserver::class])]
class BiometricDevice extends Model
{
    use AssertsTransactionalAuditSink;
    use HasFactory;

    public const NAVIGATION_BADGE_CACHE_KEY = 'nav_badge:biometric_device:tenant_id:';

    public const COMM_KEY_LENGTH = 6;

    public const COMM_KEY_PASTE_BUFFER = 20;

    public const COMM_KEY_MIN_VALUE = 100000;

    public const COMM_KEY_MAX_VALUE = 999999;

    public const PBKDF2_HASH_PREFIX = 'pbkdf2-sha256';

    public const MIN_PBKDF2_ITERATIONS = 100000;

    public const MAX_PBKDF2_ITERATIONS = 1000000;

    /**
     * Template ids parked as erasure obligations by the last `deleting` hook.
     *
     * Captured on the instance rather than persisted: it is the hand-off between
     * the pre-delete tombstone and the in-transaction audit entry.
     *
     * @var list<int>
     */
    private array $parkedTemplateIds = [];

    /**
     * Template ids provably never delivered and converged to `Deleted` on deletion.
     *
     * @var list<int>
     */
    private array $writtenOffTemplateIds = [];

    /**
     * Ledger rows terminalised because the terminal they targeted no longer exists.
     *
     * @var array<int, int>
     */
    private array $cancelledCommandIds = [];

    /**
     * Delivered `user_add` rows deliberately left `SENT` as durable delivery evidence.
     *
     * @var array<int, int>
     */
    private array $preservedCommandIds = [];

    protected $fillable = [
        'gym_id',
        'device_name',
        'device_serial',
        'device_type',
        'provider_type',
        'provider_config',
        'status',
        'is_enabled',
        'purpose',
        'adms_enabled',
        'assigned_staff_id',
        'last_heartbeat_at',
        'device_token',
        'token_hash',
        'created_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'adms_comm_key_hash',
        'adms_comm_key_salt',
        'provider_config',
        'device_token',
        'token_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array{
     *     provider_config: 'App\Casts\ProviderConfigCast',
     *     last_heartbeat_at: 'datetime',
     *     device_token: 'hashed',
     *     status: 'App\Enums\BiometricDeviceStatusEnum',
     *     is_enabled: 'boolean',
     *     provider_type: 'App\Enums\BiometricProvidersEnum',
     *     purpose: 'App\Enums\BiometricDevicePurpose',
     *     adms_enabled: 'boolean',
     *     gym_id: 'integer',
     * }
     */
    protected function casts(): array
    {
        return [
            'provider_config' => ProviderConfigCast::class,
            'last_heartbeat_at' => 'datetime',
            'device_token' => 'hashed',
            'status' => BiometricDeviceStatusEnum::class,
            'is_enabled' => 'boolean',
            'provider_type' => BiometricProvidersEnum::class,
            'purpose' => BiometricDevicePurpose::class,
            'adms_enabled' => 'boolean',
            'gym_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $device): void {
            $device->deriveProviderConfigProvenance();
        });

        static::deleting(static function (self $device): void {
            // Order is load-bearing: the tombstone reads `SENT` `user_add` rows as delivery
            // evidence, so the ledger must not be cancelled before it has decided.
            $device->tombstoneEnrolledTemplates();
            $device->cancelOrphanedCommands();
        });

        static::deleted(static function (self $device): void {
            $device->recordDeletionAudit();
        });
    }

    /**
     * Delete the terminal and its credential tombstones atomically.
     *
     * The `deleting` hook parks each credential the terminal may still hold, cancels the
     * ledger rows that can never be delivered again, and the `deleted` hook writes the
     * audit entry. All of it must commit or roll back with the row itself: a parked
     * template that survives a failed delete advertises an erasure obligation for a
     * terminal that still exists, and an audit entry written before the delete commits
     * would record a deletion that never happened. The transaction is opened here so every
     * model path — the row action, the bulk action and a direct `delete()` — shares it.
     *
     * A `deleting` listener can halt the delete by returning `false`, which Eloquent
     * reports as a normal `false` return *after* the tombstone has already written. That
     * halt is converted into a rollback here, so the parked templates and the cancelled
     * ledger rows go back with it and the caller receives `false`.
     */
    public function delete(): ?bool
    {
        try {
            return DB::transaction(function (): bool {
                // Lock the device row for the duration of the transaction. A command insert
                // that starts between the cancellation sweep and the DELETE takes a shared
                // lock on this row for its foreign-key check; the exclusive lock here makes
                // it wait, then fail once the row is gone. Without it a `PENDING` command
                // could be created after the sweep and be orphaned by `nullOnDelete()`.
                $locked = static::query()->whereKey($this->getKey())->lockForUpdate()->first();

                if ($locked === null) {
                    // A second `delete()` on a model whose row is already gone would run
                    // the whole tombstone/audit unit again and write a duplicate deletion
                    // entry, because `Model::delete()` reports success even when the
                    // DELETE affects zero rows.
                    return false;
                }

                if (parent::delete() !== true) {
                    throw DeviceDeletionVetoedException::whileDeleting();
                }

                return true;
            });
        } catch (DeviceDeletionVetoedException) {
            return false;
        }
    }

    /**
     * Delegate a "quiet" delete to the audited path.
     *
     * `Model::deleteQuietly()` runs the delete inside `withoutEvents()`, which mutes the
     * `deleting` and `deleted` hooks — so it used to commit a terminal delete with no
     * credential tombstone, no cancelled commands and no audit entry, while returning
     * `true`. This is a compliance-critical path, so the quiet variant must not be a
     * bypass. Query-builder deletes (`BiometricDevice::query()->delete()`) still bypass
     * model events; callers must not use them for this model.
     */
    public function deleteQuietly(): bool
    {
        return $this->delete() === true;
    }

    /**
     * The terminal-specific ADMS wire schema, or null when it inherits the
     * global `biometrics.adms.user_table` default.
     *
     * Only the current key (`adms_user_table`) is read. The speculative
     * `user_table` fallback that shipped in this changeset was removed: nothing
     * ever wrote it, so it could only shadow the global default with a stale
     * value. An unreadable ciphertext is treated as "no override" instead of
     * throwing on the command-enqueue path; {@see ProviderConfigCast} returns
     * null for unreadable ciphertext, so there is no exception to catch.
     */
    public function admsUserTableOverride(): ?AdmsUserTableEnum
    {
        $config = is_array($this->provider_config) ? $this->provider_config : [];

        return AdmsUserTableEnum::tryParse($config['adms_user_table'] ?? null);
    }

    /**
     * Split this device's candidates into the rows the delete hook parks and the rows it
     * writes off, using the single evidence rule.
     *
     * Delivery is resolved for the whole candidate set in one ledger query rather than one
     * `wasDeliveredToHardware()` per row: the modal and the hook both run this on a device
     * with many enrolled templates, and the N+1 was the cost the modal could not afford.
     *
     * @return array{parked: Collection<int, BiometricTemplate>, written_off: Collection<int, BiometricTemplate>}
     */
    public function partitionTemplatesForDeletion(): array
    {
        $candidates = $this->templates()->parkableByDeviceDeletion()->get();

        if ($candidates->isEmpty()) {
            return ['parked' => $candidates, 'written_off' => $candidates];
        }

        $deliveredIds = $this->deliveredTemplateIds();

        $isDelivered = static function (BiometricTemplate $template) use ($deliveredIds): bool {
            $id = $template->getKey();

            return is_numeric($id) && isset($deliveredIds[(int) $id]);
        };

        $isParkable = fn (BiometricTemplate $template): bool => $template->mayBeHeldByHardware() || $isDelivered($template);

        return [
            'parked' => $candidates->filter($isParkable)->values(),
            'written_off' => $candidates->reject($isParkable)->values(),
        ];
    }

    /**
     * The templates that deleting this terminal will park for manual erasure.
     *
     * @return Collection<int, BiometricTemplate>
     */
    public function parkedTemplatesForDeletion(): Collection
    {
        return $this->partitionTemplatesForDeletion()['parked'];
    }

    public function parkedTemplateCount(): int
    {
        return $this->parkedTemplatesForDeletion()->count();
    }

    /**
     * Template ids whose own `user_add` reached this terminal, in one query.
     *
     * Every canonical key embeds the template id as its last segment, so the ledger is
     * parseable without a join — the same fact {@see BiometricTemplate::wasDeliveredToHardware()}
     * uses to survive a null foreign key.
     *
     * @return array<int, true>
     */
    private function deliveredTemplateIds(): array
    {
        $commands = AdmsDeviceCommand::query()
            ->where('device_id', $this->id)
            ->where('command_type', 'user_add')
            ->whereIn('status', [
                AdmsCommandStatusEnum::SENT,
                AdmsCommandStatusEnum::ACKNOWLEDGED,
            ])
            ->get(['idempotency_key']);

        $ids = [];

        foreach ($commands as $command) {
            $key = $command->idempotency_key;

            if (! is_string($key)) {
                continue;
            }

            $templateId = AdmsCommandKey::tryParse($key)?->templateId;

            if ($templateId !== null) {
                $ids[$templateId] = true;
            }
        }

        return $ids;
    }

    /**
     * Convert every hardware template the deleted terminal may still hold into an
     * operator erasure obligation.
     *
     * Deleting the device row does not touch the terminal's flash, so the
     * credential may still be held by the hardware. Writing `Deleted` here (or
     * letting the null foreign key mean "never delivered") is a false attestation
     * of biometric erasure. The evidence rule is
     * {@see BiometricTemplate::hasDurableHardwareEvidence()}: a row that may be on
     * the terminal, or that the command ledger proves was delivered, is parked at
     * {@see BiometricTemplateSyncStatusEnum::DeletionUnresolvable} — excluded from
     * the automated reconciler and surfaced in the "Needs Operator" filter — and
     * flagged as an erasure obligation so a later enrolment cannot supersede it.
     *
     * A row that the ledger proves never reached the hardware cannot be in flash,
     * so it converges to `Deleted` instead. Either way the ids are captured for
     * {@see self::recordDeletionAudit()}.
     *
     * The `nullOnDelete` foreign key still nulls `enrolled_by_device_id` after
     * this hook. That is referential integrity for writers that bypass model
     * events; it is never treated as evidence that the credential was erased.
     */
    private function tombstoneEnrolledTemplates(): void
    {
        $this->parkedTemplateIds = [];
        $this->writtenOffTemplateIds = [];

        // One candidate scope and one delivery query, shared with the delete modal.
        // The modal used to pre-filter `liveOrPendingCredentialStates()` only, so a
        // device whose sole credential was already `DeletionUnresolvable` promised
        // "no live enrolled templates" while this hook re-parked it.
        ['parked' => $parked, 'written_off' => $writtenOff] = $this->partitionTemplatesForDeletion();

        foreach ($parked as $template) {
            // One shared transition owns the "terminal state + obligation flag" invariant;
            // the flag is a compliance discriminator and is deliberately not mass-assignable.
            $template->parkAsUnresolvableObligation('terminal deleted while it may still hold the credential', [
                'device_id' => $this->id,
                'device_serial' => $this->device_serial,
            ]);

            $id = $template->getKey();

            if (is_numeric($id)) {
                $this->parkedTemplateIds[] = (int) $id;
            }
        }

        foreach ($writtenOff as $template) {
            // No acknowledgement, no terminal-held state and no ledger evidence: the
            // credential never left the queue, so there is nothing in flash to erase.
            $template->markErasureDischarged();

            $id = $template->getKey();

            if (is_numeric($id)) {
                $this->writtenOffTemplateIds[] = (int) $id;
            }
        }

        if ($this->parkedTemplateIds !== []) {
            Log::warning('BiometricDevice: terminal deleted while it may still hold biometric credentials; templates parked for manual erasure', [
                'device_id' => $this->id,
                'gym_id' => $this->gym_id,
                'device_serial' => $this->device_serial,
                'template_ids' => $this->parkedTemplateIds,
            ]);
        }
    }

    /**
     * Terminalise every ledger row this terminal can never deliver again.
     *
     * The foreign key nulls when the delete commits, so a `PENDING` command would
     * otherwise have no device to lease it, no terminal to poll it, and no path back to
     * a terminal state. This method therefore terminalises `PENDING` rows as `CANCELLED`
     * and delivered (`SENT`) rows as `FAILED`, both carrying
     * {@see AdmsDeviceCommand::TERMINAL_DELETED_CODE} so a cancelled orphan is
     * distinguishable from an operator cancellation.
     *
     * A `SENT` `user_add` is deliberately left `SENT`: it is the durable evidence
     * {@see BiometricTemplate::wasDeliveredToHardware()} probes after the foreign key is
     * nulled. Cancelling it (as this method used to) destroyed the only proof that a
     * credential reached the terminal, and the abandoned-placeholder sweep then wrote off
     * a live credential. An undelivered `PENDING` `user_add` is still cancelled, because
     * the terminal provably never received it.
     *
     * The idempotency key is deliberately preserved: it is the durable evidence of what
     * the row was for, and a replacement terminal uses a device-scoped key of its own.
     */
    private function cancelOrphanedCommands(): void
    {
        $this->cancelledCommandIds = [];
        $this->preservedCommandIds = [];

        $candidates = AdmsDeviceCommand::query()
            ->where('device_id', $this->id)
            ->whereIn('status', [AdmsCommandStatusEnum::PENDING, AdmsCommandStatusEnum::SENT])
            ->get(['id', 'status', 'command_type']);

        $cancelIds = [];
        $failIds = [];

        foreach ($candidates as $command) {
            $id = $command->id;

            if ($id <= 0) {
                continue;
            }

            if ($command->command_type === 'user_add' && $command->status === AdmsCommandStatusEnum::SENT) {
                $this->preservedCommandIds[] = $id;

                continue;
            }

            if ($command->status === AdmsCommandStatusEnum::SENT) {
                $failIds[] = $id;

                continue;
            }

            $cancelIds[] = $id;
        }

        if ($cancelIds !== []) {
            AdmsDeviceCommand::query()
                ->whereIn('id', $cancelIds)
                ->where('status', AdmsCommandStatusEnum::PENDING)
                ->update([
                    'status' => AdmsCommandStatusEnum::CANCELLED,
                    'device_return_code' => AdmsDeviceCommand::TERMINAL_DELETED_CODE,
                    'leased_at' => null,
                    'expires_at' => null,
                ]);
        }

        if ($failIds !== []) {
            AdmsDeviceCommand::query()
                ->whereIn('id', $failIds)
                ->where('status', AdmsCommandStatusEnum::SENT)
                ->update([
                    'status' => AdmsCommandStatusEnum::FAILED,
                    'device_return_code' => AdmsDeviceCommand::TERMINAL_DELETED_CODE,
                    'leased_at' => null,
                    'expires_at' => null,
                ]);
        }

        if ($this->preservedCommandIds !== []) {
            // The marker is what lets the reclaim sweep and `prunable()` tell this row
            // apart from a live in-flight `SENT` command after the foreign key is nulled.
            AdmsDeviceCommand::query()
                ->whereIn('id', $this->preservedCommandIds)
                ->where('status', AdmsCommandStatusEnum::SENT)
                ->update(['preserved_evidence_at' => now()]);
        }

        $this->cancelledCommandIds = [...$cancelIds, ...$failIds];

        if ($this->cancelledCommandIds === [] && $this->preservedCommandIds === []) {
            return;
        }

        // A terminal offline for weeks can carry hundreds of rows. The ids are not copied
        // into the durable audit entry: the set is reconstructible from the ledger itself
        // (cancelled rows carry `terminal_deleted`, preserved rows carry
        // `preserved_evidence_at`, both scoped to this gym and the deletion timestamp).
        Log::warning('BiometricDevice: terminal deleted with ledger rows that can never be delivered again', [
            'device_id' => $this->id,
            'gym_id' => $this->gym_id,
            'device_serial' => $this->device_serial,
            'cancelled_command_count' => count($this->cancelledCommandIds),
            'preserved_delivered_user_add_count' => count($this->preservedCommandIds),
        ]);
    }

    /**
     * Write the deletion audit entry, unconditionally and inside the delete transaction.
     *
     * The entry is written even when no templates were touched, because "this
     * terminal was deleted and it held no credentials" is itself an auditable
     * fact. It is deliberately not wrapped in a `try/catch`: the log is optional
     * as a feature, but a deletion that cannot be audited must not commit, so the
     * exception propagates and {@see self::delete()} rolls the transaction back.
     *
     * That guarantee only holds when the sink actually participates in this
     * transaction, so it is enforced rather than assumed. `ACTIVITY_LOGGER_ENABLED=false`
     * makes `ActivityLogger::log()` return `null` without writing anything, and a
     * separate `activitylog.database_connection` commits the entry on another
     * connection — in both cases the delete would commit unaudited if this method
     * only relied on an exception. The refusal is explicit: configure a sink that
     * shares the connection, or delete the terminal through a path that can write
     * one.
     */
    private function recordDeletionAudit(): void
    {
        $this->assertAuditSinkIsTransactional();

        activity()
            ->performedOn($this)
            ->withProperties([
                'gym_id' => $this->gym_id,
                'action' => 'biometric_device_deleted',
                'device_serial' => $this->device_serial,
                'template_ids' => $this->parkedTemplateIds,
                'written_off_template_ids' => $this->writtenOffTemplateIds,
                'cancelled_command_count' => count($this->cancelledCommandIds),
                'preserved_delivered_user_add_count' => count($this->preservedCommandIds),
                // The full id set is queryable from the ledger: cancelled rows carry
                // `terminal_deleted`, preserved rows carry `preserved_evidence_at`.
                'evidence_recorded_at' => now()->toIso8601String(),
            ])
            ->log('Biometric terminal deleted');
    }

    /**
     * `saveQuietly()`/`updateQuietly()` bypass model events, including the
     * `saving` hook that records cloud-key provenance. Run the same derivation
     * before a quiet save so a quiet cloud→native switch cannot make a
     * preserved provider credential look like an ADMS alias.
     *
     * @param  array<array-key, mixed>  $options
     */
    public function saveQuietly(array $options = []): bool
    {
        $this->deriveProviderConfigProvenance();

        return parent::saveQuietly($options);
    }

    private function deriveProviderConfigProvenance(): void
    {
        // A new row has no previous provider policy: its `original` attributes
        // are empty, so every value looks dirty. Derivation only applies to a
        // transition of an already-persisted row.
        if (! $this->exists) {
            return;
        }

        if (! $this->isDirty('provider_type')) {
            return;
        }

        $originalType = $this->getOriginal('provider_type');
        $newType = $this->provider_type;

        // `null` is the legacy native shape. Treating it as the same
        // policy as DeviceNative keeps an explicit null→device_native
        // selection from looking like a provider change (and from
        // demanding a config replacement that is not needed).
        if (self::providerPolicyKey($originalType) === self::providerPolicyKey($newType)) {
            return;
        }

        // Every Eloquent writer, not only the Filament page, must either
        // preserve a readable config or explicitly replace an unreadable
        // one. Otherwise provenance cannot be recorded and a recoverable
        // ciphertext could be silently discarded.
        if ($this->providerConfigIsUnreadable() && ! $this->isDirty('provider_config')) {
            throw UnreadableProviderConfigException::whileChangingProviderType();
        }

        // A device-native `comm_key` is an ADMS alias. Leaving it in place when
        // the provider becomes cloud makes every ADMS path stop reading it,
        // and a later native switch would mark it as a retained cloud
        // credential. `pushcommkey` is valid for every provider, so translate
        // the only plaintext key before it is stranded.
        if (self::providerTypeIsDeviceNative($originalType) && ! self::providerTypeIsDeviceNative($newType)) {
            $this->moveAdmsCommKeyToPushAlias();

            return;
        }

        if (! self::providerTypeIsDeviceNative($newType) || self::providerTypeIsDeviceNative($originalType)) {
            return;
        }

        try {
            $config = is_array($this->provider_config) ? $this->provider_config : [];
        } catch (Throwable) {
            return;
        }

        if (! array_key_exists('comm_key', $config)) {
            return;
        }

        $config['comm_key_source'] = 'cloud';
        $this->provider_config = $config;
    }

    /**
     * Translate a device-native `comm_key` ADMS alias into `pushcommkey` when
     * the provider leaves device-native mode.
     *
     * `pushcommkey` stays an ADMS alias for cloud providers, so the terminal
     * keeps authenticating and a return switch to device-native does not mark
     * the key as a cloud credential. An existing `pushcommkey` always wins and
     * a cloud-marked `comm_key` is a provider credential, never an ADMS alias;
     * in both cases the config is left untouched. An unreadable config has
     * already been refused by the caller and is tolerated defensively.
     */
    private function moveAdmsCommKeyToPushAlias(): void
    {
        try {
            $config = is_array($this->provider_config) ? $this->provider_config : [];
        } catch (Throwable) {
            return;
        }

        if (array_key_exists('pushcommkey', $config)) {
            return;
        }

        if (($config['comm_key_source'] ?? null) === 'cloud') {
            return;
        }

        $commKey = $config['comm_key'] ?? null;

        if (self::firstFilledCommKey($commKey) === null) {
            return;
        }

        $config['pushcommkey'] = $commKey;
        unset($config['comm_key']);

        $this->provider_config = $config;
    }

    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<BiometricTemplate, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(BiometricTemplate::class, 'enrolled_by_device_id');
    }

    /**
     * @return HasMany<DoorEvent, $this>
     */
    public function doorEvents(): HasMany
    {
        return $this->hasMany(DoorEvent::class, 'device_id');
    }

    /**
     * @return HasMany<AdmsDeviceCommand, $this>
     */
    public function admsCommands(): HasMany
    {
        return $this->hasMany(AdmsDeviceCommand::class, 'device_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', BiometricDeviceStatusEnum::Active)
            ->where('is_enabled', true);
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
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function admsEnabled(Builder $query): void
    {
        $query->where('adms_enabled', true);
    }

    /**
     * O(1) Serial Number lookup with Redis caching for ADMS devices.
     */
    public static function findBySerial(string $serial): ?self
    {
        if (blank($serial) || strlen($serial) > 64) {
            return null;
        }

        $cacheKey = "biometric:device:serial:{$serial}";

        try {
            $cachedId = Cache::get($cacheKey);
        } catch (Throwable $exception) {
            Log::warning('ADMS: serial lookup cache unavailable; falling back to the database', [
                'exception' => $exception::class,
            ]);
            $cachedId = null;
        }

        if ($cachedId !== null && $cachedId > 0) {
            /** @var self|null $cachedDevice */
            $cachedDevice = self::query()->find($cachedId);
            if ($cachedDevice && $cachedDevice->device_serial === $serial && $cachedDevice->is_enabled && $cachedDevice->adms_enabled) {
                return $cachedDevice;
            }

            try {
                Cache::forget($cacheKey);
            } catch (Throwable) {
                // The database path below still authorizes the request.
            }
        }

        /** @var self|null $device */
        $device = self::query()
            ->where('device_serial', $serial)
            ->where('adms_enabled', true)
            ->where('is_enabled', true)
            ->first();

        if ($device) {
            try {
                Cache::put($cacheKey, $device->id, 86400);
            } catch (Throwable) {
                // Caching is an optimization only.
            }

            return $device;
        }

        return null;
    }

    public function verifyToken(string $plainToken): bool
    {
        return $this->device_token !== null && Hash::check($plainToken, $this->device_token);
    }

    /**
     * O(1) Token seek via SHA-256 digest with Redis caching and legacy fallback.
     */
    public static function findByPlainToken(string $plainToken): ?self
    {
        if (blank($plainToken)) {
            return null;
        }

        $tokenHash = hash('sha256', $plainToken);
        $cacheKey = "biometric:device:token:{$tokenHash}";

        // Fast-path: check cache for device ID
        $cachedId = Cache::get($cacheKey);
        if ($cachedId === 0) {
            return null;
        }

        if ($cachedId !== null) {
            /** @var self|null $cachedDevice */
            $cachedDevice = self::query()->find($cachedId);
            if ($cachedDevice && $cachedDevice->token_hash === $tokenHash) {
                return $cachedDevice;
            }

            Cache::forget($cacheKey);
        }

        // Indexed search by token_hash
        /** @var self|null $device */
        $device = self::query()
            ->where('token_hash', $tokenHash)
            ->first();

        if ($device) {
            Cache::put($cacheKey, $device->id, 86400);

            return $device;
        }

        // Backward compatibility fallback for legacy devices without token_hash populated.
        // Bounded and cached to protect against unbounded bcrypt workloads (Should-Fix 1).
        $hasLegacyDevices = Cache::remember('biometric:devices:has_legacy_tokens', 3600, function (): bool {
            return self::query()->whereNull('token_hash')->whereNotNull('device_token')->exists();
        });

        if ($hasLegacyDevices) {
            $legacyDevices = self::query()
                ->whereNull('token_hash')
                ->whereNotNull('device_token')
                ->orderBy('id')
                ->limit(10)
                ->get();

            foreach ($legacyDevices as $legacy) {
                if ($legacy->verifyToken($plainToken)) {
                    // Auto-upgrade legacy device to indexed token_hash!
                    $legacy->update(['token_hash' => $tokenHash]);
                    Cache::forget('biometric:devices:has_legacy_tokens');
                    Cache::put($cacheKey, $legacy->id, 86400);

                    return $legacy;
                }
            }
        }

        return null;
    }

    /**
     * Check if this device has an ADMS pushcommkey configured (either via hash or provider_config).
     *
     * A corrupt/unreadable `provider_config` must not turn an authentication or
     * Filament request into a 500. Only a real hash can be trusted in that case.
     */
    public function hasCommKeyConfigured(): bool
    {
        try {
            return $this->hasRealCommKeyHash() || $this->admsPlaintextCommKey() !== null;
        } catch (Throwable $exception) {
            Log::error('ADMS: provider_config could not be read while checking the comm key, falling back to the stored hash', [
                'device_id' => $this->getKey(),
                'exception' => $exception::class,
            ]);

            return $this->hasRealCommKeyHash();
        }
    }

    /**
     * Whether the encrypted provider_config exists but cannot be decrypted.
     *
     * UI callers use this to show an actionable "unreadable" state instead of
     * rendering a misleading "not configured" state or throwing a 500.
     */
    public function providerConfigIsUnreadable(): bool
    {
        $raw = $this->getRawOriginal('provider_config');

        if (! is_string($raw) || $raw === '') {
            return false;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($raw), true, 512, JSON_THROW_ON_ERROR);

            return ! is_array($decoded);
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * A real verifier is a non-empty hash. Legacy rows may contain '' or whitespace.
     */
    public function hasRealCommKeyHash(): bool
    {
        return filled($this->adms_comm_key_hash);
    }

    /**
     * Resolve the legacy plaintext ADMS key stored in provider_config.
     *
     * `pushcommkey` is the ADMS key for every provider. `comm_key` is an ADMS
     * alias only for device-native/dual-mode devices (null or DeviceNative
     * provider type). For cloud providers it is a provider credential and is
     * never read or deleted by the ADMS paths.
     */
    public function admsPlaintextCommKey(): ?string
    {
        $raw = $this->admsPlaintextCommKeyRaw();

        return is_string($raw) || is_numeric($raw) ? (string) $raw : null;
    }

    /**
     * The raw legacy value, preserving its type so the backfill can refuse a
     * numeric JSON value that may have lost a leading zero or precision.
     */
    public function admsPlaintextCommKeyRaw(): mixed
    {
        $aliases = $this->admsPlaintextCommKeyAliases();

        return $aliases['pushcommkey'] ?? $aliases['comm_key'] ?? null;
    }

    /**
     * Every ADMS alias present in provider_config, including empty values.
     *
     * Used by the backfill to distinguish "no alias" from "an alias that
     * normalizes to empty" (which must be refused rather than provisioned
     * around silently).
     *
     * @return array{pushcommkey?: mixed, comm_key?: mixed}
     */
    public function rawCommKeyAliases(): array
    {
        try {
            $config = is_array($this->provider_config) ? $this->provider_config : [];
        } catch (Throwable) {
            return [];
        }

        $aliases = [];

        if (array_key_exists('pushcommkey', $config)) {
            $aliases['pushcommkey'] = $config['pushcommkey'];
        }

        if ($this->isDeviceNative() && ! $this->commKeySourceIsCloudProvider() && array_key_exists('comm_key', $config)) {
            $aliases['comm_key'] = $config['comm_key'];
        }

        return $aliases;
    }

    /**
     * The filled legacy ADMS aliases in provider_config, preserving raw types.
     *
     * @return array{pushcommkey?: mixed, comm_key?: mixed}
     */
    public function admsPlaintextCommKeyAliases(): array
    {
        try {
            $config = is_array($this->provider_config) ? $this->provider_config : [];
        } catch (Throwable $exception) {
            Log::error('ADMS: provider_config could not be decrypted while reading plaintext aliases', [
                'device_id' => $this->getKey(),
                'exception' => $exception::class,
            ]);

            return [];
        }

        $aliases = [];

        $pushKey = $config['pushcommkey'] ?? null;
        if (self::firstFilledCommKey($pushKey) !== null) {
            $aliases['pushcommkey'] = $pushKey;
        }

        if ($this->isDeviceNative() && ! $this->commKeySourceIsCloudProvider()) {
            $commKey = $config['comm_key'] ?? null;
            if (self::firstFilledCommKey($commKey) !== null) {
                $aliases['comm_key'] = $commKey;
            }
        }

        return $aliases;
    }

    /**
     * Whether `provider_config['comm_key']` is a cloud provider credential
     * preserved across a cloud→native switch rather than an ADMS alias.
     */
    public function commKeySourceIsCloudProvider(): bool
    {
        try {
            $config = is_array($this->provider_config) ? $this->provider_config : [];
        } catch (Throwable) {
            return false;
        }

        return ($config['comm_key_source'] ?? null) === 'cloud';
    }

    /**
     * Whether the legacy `comm_key` alias may be deleted by ADMS cleanup.
     *
     * `provider_type = null` is the legacy native/dual-mode shape. It is treated
     * as device-native everywhere else (`isDeviceNative()`), so cleanup applies
     * the same policy and records the aliases it removed in the activity log.
     */
    public function mayDeleteCommKeyAlias(): bool
    {
        return self::providerTypeIsDeviceNative($this->provider_type)
            && ! $this->commKeySourceIsCloudProvider();
    }

    public function hasAmbiguousCommKeyAliases(): bool
    {
        $aliases = $this->admsPlaintextCommKeyAliases();

        $pushKey = $aliases['pushcommkey'] ?? null;
        $commKey = $aliases['comm_key'] ?? null;

        return $pushKey !== null
            && $commKey !== null
            && self::sanitizeCommKey($pushKey) !== self::sanitizeCommKey($commKey);
    }

    public static function missCounterCacheKey(int|string $deviceId): string
    {
        return "biometric_device:consecutive_misses:{$deviceId}";
    }

    /**
     * Clear the heartbeat miss counter, tolerating a cache outage.
     */
    public static function clearMissCounter(int|string $deviceId): void
    {
        try {
            Cache::forget(self::missCounterCacheKey($deviceId));
        } catch (Throwable) {
            // Heartbeat bookkeeping must not turn a valid ADMS request into a 500.
        }
    }

    /**
     * Generate a hardware-compatible six-digit numeric communication key.
     *
     * The lower bound intentionally excludes `0` so firmware that parses the
     * key as an integer cannot truncate a leading zero before transmission.
     */
    public static function generateCommKey(): string
    {
        return (string) random_int(self::COMM_KEY_MIN_VALUE, self::COMM_KEY_MAX_VALUE);
    }

    /**
     * Normalize an operator- or device-supplied communication key.
     *
     * Removes whitespace (ASCII and Unicode), format characters such as
     * zero-width spaces/joiners, soft hyphens, byte-order marks, word joiners,
     * and NUL bytes. Non-scalar values (other than Stringable) normalize to an
     * empty string. This method does not enforce the length or charset policy.
     */
    public static function sanitizeCommKey(mixed $rawKey): string
    {
        if ($rawKey instanceof Stringable) {
            $value = (string) $rawKey;
        } elseif (is_scalar($rawKey)) {
            $value = (string) $rawKey;
        } else {
            return '';
        }

        if (! mb_check_encoding($value, 'UTF-8')) {
            return '';
        }

        return preg_replace('/[\s\p{Z}\p{Cf}\x{0000}]+/u', '', $value) ?? '';
    }

    /**
     * Validate a *new* operator-supplied key.
     *
     * Legacy verifiers may contain longer or different keys; this policy only
     * governs keys written through the Filament form, page hooks, or generation.
     * Returns null when valid, otherwise the operator-facing error message.
     */
    public static function commKeyValidationError(mixed $rawKey): ?string
    {
        $sanitized = self::sanitizeCommKey($rawKey);

        if ($sanitized === '') {
            return 'The ADMS Communication Key is required.';
        }

        if (mb_strlen($sanitized) !== self::COMM_KEY_LENGTH) {
            return 'The ADMS Communication Key must be exactly ' . self::COMM_KEY_LENGTH . ' characters after whitespace is removed.';
        }

        if (preg_match('/^[A-Za-z0-9]+$/', $sanitized) !== 1) {
            return 'The ADMS Communication Key may contain only letters and numbers.';
        }

        if (ctype_digit($sanitized) && str_starts_with($sanitized, '0')) {
            return 'A numeric ADMS Communication Key cannot start with 0.';
        }

        return null;
    }

    public static function isValidCommKey(mixed $rawKey): bool
    {
        return self::commKeyValidationError($rawKey) === null;
    }

    public static function generateCommKeySalt(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Hash a communication key with PBKDF2-SHA256.
     *
     * The returned verifier is self-describing (`pbkdf2-sha256:{iterations}:{hash}`)
     * so changing `ADMS_PBKDF2_ITERATIONS` cannot invalidate hashes created by an
     * earlier deploy. Legacy unversioned hashes remain verifiable.
     */
    public static function hashCommKey(string $plainKey, string $salt, ?int $iterations = null): string
    {
        $iterations ??= self::effectivePbkdf2Iterations();

        // Tests use a low count to keep the suite fast; every other environment
        // enforces the secure floor even if the config is misconfigured, and no
        // environment is allowed to turn a storage row into a CPU denial of service.
        $iterations = self::clampPbkdf2Iterations($iterations);

        /** @var positive-int $iterations */
        return self::PBKDF2_HASH_PREFIX . ':' . $iterations . ':' . hash_pbkdf2('sha256', $plainKey, $salt, $iterations);
    }

    public static function clampPbkdf2Iterations(int $iterations): int
    {
        return min(self::MAX_PBKDF2_ITERATIONS, max(self::pbkdf2Floor(), $iterations));
    }

    /**
     * Perform the same PBKDF2 work as a real verification without revealing whether
     * the supplied serial exists. Used by the authenticator to blunt timing-based
     * serial enumeration.
     */
    public static function performDummyCommKeyVerification(string $candidate): void
    {
        $sanitized = self::sanitizeCommKey(substr($candidate, 0, 256));

        $iterations = self::effectivePbkdf2Iterations();

        /** @var positive-int $iterations */
        $digest = hash_pbkdf2('sha256', $sanitized === '' ? 'invalid' : $sanitized, 'adms-unknown-device', $iterations);

        // The digest is intentionally not compared. Reading it keeps the
        // timing work from being optimized away or flagged as an unused call.
        if (strlen($digest) !== 64) {
            Log::warning('ADMS: dummy comm key verification produced an unexpected digest length');
        }
    }

    public static function effectivePbkdf2Iterations(): int
    {
        $configured = config()->integer('biometrics.adms.pbkdf2_iterations', self::MIN_PBKDF2_ITERATIONS);

        return min(self::MAX_PBKDF2_ITERATIONS, max(self::pbkdf2Floor(), $configured));
    }

    /**
     * The verification floor.
     *
     * Production always uses the 100,000 constant. The test suite lowers it at
     * runtime so crypto tests stay fast; the override is ignored outside the
     * testing environment, so a misconfigured deployment cannot weaken stored
     * verifiers or lock out the fleet by raising the floor above a hash.
     */
    private static function pbkdf2Floor(): int
    {
        if (! app()->environment('testing')) {
            return self::MIN_PBKDF2_ITERATIONS;
        }

        return min(
            self::MAX_PBKDF2_ITERATIONS,
            max(1, config()->integer('biometrics.adms.pbkdf2_min_iterations', self::MIN_PBKDF2_ITERATIONS))
        );
    }

    /**
     * Verify a plain pushcommkey against this device's verifier.
     *
     * The presented key is sanitized first. Devices provisioned before sanitation
     * existed are given a time-bounded raw-key fallback; a successful fallback
     * immediately re-hashes the sanitized form so the fallback is needed once,
     * not on every poll, and the sunset cannot strand the device.
     */
    public function verifyCommKey(string $plainKey): bool
    {
        if (strlen($plainKey) > 256) {
            return false;
        }

        $sanitizedKey = self::sanitizeCommKey($plainKey);

        if ($sanitizedKey === '') {
            return false;
        }

        if ($this->verifyAgainstStoredCommKey($sanitizedKey)) {
            $this->upgradeLegacyVerifier($sanitizedKey);

            return true;
        }

        if ($this->verifyUsingLegacyFallback($plainKey)) {
            Log::warning('ADMS: comm key matched only through the legacy unsanitized fallback; normalizing the stored verifier', [
                'device_id' => $this->getKey(),
                'serial_hash' => filled($this->device_serial) ? hash('sha256', $this->device_serial) : null,
            ]);

            $this->rehashSanitizedCommKey($sanitizedKey);

            return true;
        }

        return false;
    }

    /**
     * Check the raw (unsanitized) form without mutating the verifier.
     *
     * Used by the backfill dry-run to report a raw-only verifier without healing it.
     */
    public function verifyUsingLegacyFallback(string $plainKey): bool
    {
        if (strlen($plainKey) > 256) {
            return false;
        }

        $sanitizedKey = self::sanitizeCommKey($plainKey);

        if ($sanitizedKey === '' || $sanitizedKey === $plainKey || ! $this->legacyUnsanitizedFallbackEnabled()) {
            return false;
        }

        return $this->verifyAgainstStoredCommKey($plainKey);
    }

    /**
     * Re-hash the sanitized form of a key that only matched through the raw fallback.
     */
    public function healLegacyCommKey(string $plainKey): void
    {
        $this->rehashSanitizedCommKey(self::sanitizeCommKey($plainKey));
    }

    /**
     * Verify without the legacy raw-form fallback.
     *
     * The backfill cleanup uses this to prove the sanitized form is the stored
     * verifier before deleting the only plaintext recovery copy.
     */
    public function verifyCommKeyWithoutLegacyFallback(string $plainKey): bool
    {
        if (strlen($plainKey) > 256) {
            return false;
        }

        $sanitizedKey = self::sanitizeCommKey($plainKey);

        return $sanitizedKey !== '' && $this->verifyAgainstStoredCommKey($sanitizedKey);
    }

    private function verifyAgainstStoredCommKey(string $candidate): bool
    {
        if ($this->hasRealCommKeyHash()) {
            if (filled($this->adms_comm_key_salt)) {
                return $this->verifyPbkdf2Hash($candidate, (string) $this->adms_comm_key_hash, $this->adms_comm_key_salt);
            }

            // Legacy fallback for unsalted SHA-256 records prior to PBKDF2 migration.
            return hash_equals(strtolower((string) $this->adms_comm_key_hash), hash('sha256', $candidate));
        }

        $expectedKey = $this->admsPlaintextCommKey();

        if ($expectedKey === null) {
            return false;
        }

        if (hash_equals($expectedKey, $candidate)) {
            return true;
        }

        // Legacy plaintext keys may themselves contain whitespace. Compare the
        // normalized stored form as well as the raw form.
        $sanitizedExpected = self::sanitizeCommKey($expectedKey);

        return $sanitizedExpected !== '' && hash_equals($sanitizedExpected, $candidate);
    }

    private function verifyPbkdf2Hash(string $candidate, string $storedHash, string $salt): bool
    {
        $pattern = '/^' . preg_quote(self::PBKDF2_HASH_PREFIX, '/') . ':(\d+):([a-f0-9]{64})$/i';

        if (preg_match($pattern, $storedHash, $matches) === 1) {
            $iterations = (int) $matches[1];

            if ($iterations < self::pbkdf2Floor() || $iterations > self::MAX_PBKDF2_ITERATIONS) {
                Log::error('ADMS: stored comm key verifier has an unsafe iteration count; refusing verification', [
                    'device_id' => $this->getKey(),
                    'iterations' => $iterations,
                ]);

                return false;
            }

            /** @var positive-int $iterations */
            return hash_equals(strtolower($matches[2]), hash_pbkdf2('sha256', $candidate, $salt, $iterations));
        }

        // Legacy unversioned PBKDF2 hash: try the configured iterations and the
        // historical default so an env change does not lock out the fleet.
        $configuredIterations = self::effectivePbkdf2Iterations();

        foreach (array_unique([$configuredIterations, self::MIN_PBKDF2_ITERATIONS]) as $iterations) {
            /** @var positive-int $iterations */
            if (hash_equals(strtolower($storedHash), hash_pbkdf2('sha256', $candidate, $salt, $iterations))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Upgrade a verifier that is weaker than the current policy.
     *
     * This covers legacy unsalted SHA-256 rows and unversioned/low-iteration
     * PBKDF2 rows. The plaintext is already in memory, so no terminal change is
     * required.
     */
    private function upgradeLegacyVerifier(string $sanitizedKey): void
    {
        if (! $this->hasRealCommKeyHash()) {
            return;
        }

        if (blank($this->adms_comm_key_salt)) {
            $this->rehashSanitizedCommKey($sanitizedKey);

            return;
        }

        if ($this->storedPbkdf2Iterations() < self::effectivePbkdf2Iterations()) {
            $this->rehashSanitizedCommKey($sanitizedKey);
        }
    }

    private function storedPbkdf2Iterations(): int
    {
        $pattern = '/^' . preg_quote(self::PBKDF2_HASH_PREFIX, '/') . ':(\d+):[a-f0-9]{64}$/i';

        if (preg_match($pattern, (string) $this->adms_comm_key_hash, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function rehashSanitizedCommKey(string $sanitizedKey): void
    {
        if ($sanitizedKey === '') {
            return;
        }

        $salt = self::generateCommKeySalt();
        $newHash = self::hashCommKey($sanitizedKey, $salt);

        if (! $this->exists) {
            $this->forceFill([
                'adms_comm_key_hash' => $newHash,
                'adms_comm_key_salt' => $salt,
            ]);

            return;
        }

        $previousHash = $this->getRawOriginal('adms_comm_key_hash');
        $previousSalt = $this->getRawOriginal('adms_comm_key_salt');
        $previousHash = is_string($previousHash) ? $previousHash : null;
        $previousSalt = is_string($previousSalt) ? $previousSalt : null;

        try {
            $query = self::query()->whereKey($this->getKey());

            if ($previousHash === null) {
                $query->whereNull('adms_comm_key_hash');
            } else {
                $query->where('adms_comm_key_hash', $previousHash);
            }

            $updated = $query->update([
                'adms_comm_key_hash' => $newHash,
                'adms_comm_key_salt' => $salt,
                'updated_at' => now(),
            ]);

            if ($updated === 0) {
                // Leave the model exactly as the database has it. Otherwise the
                // dirty verifier is written back by the next ordinary save
                // (for example the controller's heartbeat update) and
                // resurrects the key we just refused to rotate.
                $this->restoreCommKeyVerifier($previousHash, $previousSalt);

                Log::warning('ADMS: comm key verifier changed during self-heal; skipped stale rehash', [
                    'device_id' => $this->getKey(),
                ]);

                return;
            }

            $this->forceFill([
                'adms_comm_key_hash' => $newHash,
                'adms_comm_key_salt' => $salt,
            ]);

            // The verifier is already persisted by the conditional update, so
            // it must not remain dirty on this instance.
            $this->syncOriginalAttributes(['adms_comm_key_hash', 'adms_comm_key_salt']);
        } catch (Throwable $exception) {
            $this->restoreCommKeyVerifier($previousHash, $previousSalt);

            // Authentication must not fail because the opportunistic verifier
            // upgrade could not be persisted (read replica, deadlock, DB outage).
            Log::warning('ADMS: failed to persist comm key verifier self-heal; authentication still succeeded', [
                'device_id' => $this->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }

    private function restoreCommKeyVerifier(?string $hash, ?string $salt): void
    {
        $this->forceFill([
            'adms_comm_key_hash' => $hash,
            'adms_comm_key_salt' => $salt,
        ]);

        $this->syncOriginalAttributes(['adms_comm_key_hash', 'adms_comm_key_salt']);
    }

    private static function firstFilledCommKey(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $stringValue = (string) $value;

        return self::sanitizeCommKey($stringValue) === '' ? null : $stringValue;
    }

    private function legacyUnsanitizedFallbackEnabled(): bool
    {
        return config()->boolean('biometrics.adms.allow_legacy_unsanitized_comm_key', true);
    }

    public function isActive(): bool
    {
        if (array_key_exists('is_enabled', $this->attributes) && ! $this->attributes['is_enabled']) {
            return false;
        }

        return $this->status === BiometricDeviceStatusEnum::Active;
    }

    public function isDeviceNative(): bool
    {
        return self::providerTypeIsDeviceNative($this->provider_type);
    }

    /**
     * Single policy for the legacy `provider_type = null` shape.
     *
     * Null is the pre-enum native/dual-mode row. Treating it as native here and
     * in cleanup keeps verification, Create, Edit, migration, and auth aligned.
     */
    public static function providerTypeIsDeviceNative(mixed $providerType): bool
    {
        if ($providerType instanceof BiometricProvidersEnum) {
            return $providerType === BiometricProvidersEnum::DeviceNative;
        }

        if (is_string($providerType)) {
            return $providerType === ''
                || $providerType === BiometricProvidersEnum::DeviceNative->value
                || $providerType === 'device_native';
        }

        return $providerType === null;
    }

    /**
     * A provider-policy identity that treats `null` and DeviceNative as equal.
     *
     * The two shapes are interchangeable in every other ADMS path, so a
     * null→device_native update must not look like a provider change to the
     * unreadable-config guard or the provenance hook.
     */
    private static function providerPolicyKey(mixed $providerType): string
    {
        if (self::providerTypeIsDeviceNative($providerType)) {
            return BiometricProvidersEnum::DeviceNative->value;
        }

        if ($providerType instanceof BiometricProvidersEnum) {
            return $providerType->value;
        }

        return is_string($providerType) ? $providerType : 'invalid';
    }

    /**
     * Whether this row's stored verifier needs the explicit reprovision path.
     *
     * Selected shapes:
     * - unsalted hashes (legacy SHA-256, or a versioned hash whose salt is gone),
     * - versioned PBKDF2 hashes with an iteration count outside the safe range,
     * - salted hashes that are not a 64-hex digest (corrupt/garbage),
     * - whitespace-only placeholders.
     *
     * A salted, unversioned 64-hex PBKDF2 hash is deliberately NOT selected.
     * Its iteration count cannot be detected from the hash, so a 100,000-count
     * hash (which authenticates against the configured/historical counts) is
     * indistinguishable from a different-count hash. Rotating the whole fleet
     * to catch the latter would break working devices; operators rotate those
     * rows explicitly instead. See {@see self::commKeyVerifierIsUnversioned()}.
     */
    public function commKeyVerifierNeedsReprovision(): bool
    {
        if (! $this->hasRealCommKeyHash()) {
            $rawHash = $this->getRawOriginal('adms_comm_key_hash');

            if (! is_string($rawHash) || $rawHash === '') {
                $rawHash = $this->adms_comm_key_hash;
            }

            // A whitespace-only hash is a legacy placeholder, not a usable
            // verifier; let --reprovision-legacy replace it as well.
            return is_string($rawHash) && $rawHash !== '';
        }

        if (blank($this->adms_comm_key_salt)) {
            return true;
        }

        $hash = (string) $this->adms_comm_key_hash;

        if (preg_match('/^' . preg_quote(self::PBKDF2_HASH_PREFIX, '/') . ':(\d+):([a-f0-9]{64})$/i', $hash, $matches) === 1) {
            $iterations = (int) $matches[1];

            return $iterations < self::pbkdf2Floor() || $iterations > self::MAX_PBKDF2_ITERATIONS;
        }

        if (preg_match('/^[a-f0-9]{64}$/i', $hash) === 1) {
            // Salted unversioned PBKDF2: usable when the count matches the
            // configured or historical 100,000 count; otherwise it cannot be
            // detected and is left for an explicit rotation.
            return false;
        }

        return true;
    }

    /**
     * A usable verifier, not merely a non-empty hash column.
     *
     * A garbage non-hex string must not be reported as "Configured". An
     * unsalted legacy SHA-256 and a salted unversioned PBKDF2 digest both
     * authenticate (the latter against the configured/historical counts), so
     * both are recognized.
     */
    public function hasRecognizedCommKeyVerifier(): bool
    {
        if (! $this->hasRealCommKeyHash()) {
            return false;
        }

        $hash = (string) $this->adms_comm_key_hash;
        $prefix = preg_quote(self::PBKDF2_HASH_PREFIX, '/');

        if (preg_match('/^' . $prefix . ':(\d+):([a-f0-9]{64})$/i', $hash, $matches) === 1) {
            $iterations = (int) $matches[1];

            // A versioned hash without a salt can never be verified:
            // verifyAgainstStoredCommKey() falls back to unsalted SHA-256 when
            // the salt is blank.
            return filled($this->adms_comm_key_salt)
                && $iterations >= self::pbkdf2Floor()
                && $iterations <= self::MAX_PBKDF2_ITERATIONS;
        }

        // 64-hex covers both shapes that verifyAgainstStoredCommKey() accepts:
        // unsalted legacy SHA-256 and salted unversioned PBKDF2.
        return preg_match('/^[a-f0-9]{64}$/i', $hash) === 1;
    }

    /**
     * Whether the verifier is a salted, unversioned PBKDF2 digest.
     *
     * The hash does not carry its iteration count, so the UI labels it
     * "Configured (unversioned)" instead of "Unrecognized verifier" while the
     * backfill leaves it alone.
     */
    public function commKeyVerifierIsUnversioned(): bool
    {
        if (! $this->hasRealCommKeyHash() || blank($this->adms_comm_key_salt)) {
            return false;
        }

        return preg_match('/^[a-f0-9]{64}$/i', (string) $this->adms_comm_key_hash) === 1;
    }

    /**
     * Whether the only ADMS verifier is the plaintext `comm_key` alias in
     * provider_config.
     *
     * Used by the edit form to require a key when switching from a
     * device-native provider (where `comm_key` is an ADMS alias) to a cloud
     * provider (where it is a provider credential and is no longer read).
     * `pushcommkey` remains a valid alias for every provider, so a row that
     * also has a `pushcommkey` does not need a new key for the switch.
     */
    public function hasOnlyPlaintextCommKeyAlias(): bool
    {
        $aliases = $this->admsPlaintextCommKeyAliases();

        return ! $this->hasRealCommKeyHash()
            && array_key_exists('comm_key', $aliases)
            && ! array_key_exists('pushcommkey', $aliases);
    }

    public function hasCloudProvider(): bool
    {
        return filled($this->provider_type)
            && $this->provider_type !== BiometricProvidersEnum::DeviceNative;
    }

    public function isAttendanceOnly(): bool
    {
        return $this->purpose === BiometricDevicePurpose::AttendanceOnly;
    }

    public function isDoorAccess(): bool
    {
        return in_array($this->purpose, [
            BiometricDevicePurpose::DoorAccess,
            BiometricDevicePurpose::DoorAccessNoAttendance,
        ], true);
    }

    public function shouldCreateAttendance(): bool
    {
        return in_array($this->purpose, [
            BiometricDevicePurpose::AttendanceOnly,
            BiometricDevicePurpose::DoorAccess,
        ], true);
    }
}
