<?php

declare(strict_types=1);

namespace App\Models;

use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Casts\AdmsUserTableCast;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\AdmsUserTableEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Traits\AssertsTransactionalAuditSink;
use Database\Factories\AdmsDeviceCommandFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * @property int $id
 * @property int $gym_id
 * @property int|null $device_id
 * @property string|null $device_serial
 * @property string $command_type
 * @property array<string, mixed>|null $command_payload
 * @property AdmsUserTableEnum|null $user_table
 * @property AdmsCommandStatusEnum $status
 * @property int|null $epoch_credential_user_add_id
 * @property int $attempt_count
 * @property int $max_attempt_count
 * @property Carbon|null $sent_at
 * @property Carbon|null $acknowledged_at
 * @property string|null $device_return_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BiometricDevice|null $device
 * @property-read Gym $gym
 *
 * @use HasFactory<AdmsDeviceCommandFactory>
 *
 * @method static Builder<static>|AdmsDeviceCommand newModelQuery()
 * @method static Builder<static>|AdmsDeviceCommand newQuery()
 * @method static Builder<static>|AdmsDeviceCommand pending()
 * @method static Builder<static>|AdmsDeviceCommand forDevice(int $deviceId)
 * @method static Builder<static>|AdmsDeviceCommand query()
 */
class AdmsDeviceCommand extends Model
{
    use AssertsTransactionalAuditSink;

    /** @use HasFactory<AdmsDeviceCommandFactory> */
    use HasFactory;

    use Prunable;

    /**
     * Return code written when a command is quarantined because its PIN can never be addressed.
     *
     * A quarantined row is terminal (`FAILED`) rather than left at `PENDING`, because an
     * unusable PIN is a permanent property of the payload, not a transient condition. Leaving
     * it leasable made fifty malformed PINs at the head of the queue a permanent head-of-line
     * block for every later command, including the `user_delete` an erasure depends on.
     */
    public const UNADDRESSABLE_PIN_CODE = 'unaddressable_pin';

    /**
     * Return code written when a command's lease expired at the delivery cap.
     *
     * The terminal was offline for the whole lease window, so the command was never
     * delivered and rejected. It is a transient condition, not abandonment: the reconciler
     * re-drives it with a fresh budget rather than parking the erasure obligation.
     */
    public const TIMEOUT_MAX_ATTEMPTS_CODE = 'timeout_max_attempts';

    /**
     * How many times a lease that expired at the delivery cap may be re-armed.
     *
     * The premise behind the first re-arm — the terminal was offline for the whole lease
     * window — stops being safe once the terminal has polled and been handed the command
     * repeatedly. After this bound the row is treated as potentially delivered: the
     * template is parked as an erasure obligation and surfaced to an operator instead of
     * being re-armed forever.
     */
    public const MAX_TRANSIENT_REARMS = 2;

    /**
     * Return code written when a terminal was deleted while a command was still queued.
     *
     * The row is terminalised so it cannot stay `PENDING` forever, but the code records
     * why: the command can never be delivered because its target no longer exists.
     */
    public const TERMINAL_DELETED_CODE = 'terminal_deleted';

    /**
     * Get the prunable model query for old acknowledged and failed commands.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $retentionDays = config()->integer('biometrics.adms.command_retention_days', 90);
        $cutoff = now()->subDays($retentionDays);

        return static::query()
            ->whereIn('status', [AdmsCommandStatusEnum::ACKNOWLEDGED, AdmsCommandStatusEnum::FAILED, AdmsCommandStatusEnum::CANCELLED])
            // Preserved evidence is retained for as long as the obligation it proves is
            // outstanding. It is not an in-flight row and must never age out.
            ->whereNull('preserved_evidence_at')
            ->where(function (Builder $query) use ($cutoff) {
                $query->where('acknowledged_at', '<=', $cutoff)
                    ->orWhere(function (Builder $sub) use ($cutoff) {
                        $sub->whereNull('acknowledged_at')
                            ->where('updated_at', '<=', $cutoff);
                    });
            })
            // Retain ever-exhausted rows: they are the only durable evidence that a GDPR
            // erasure was attempted and abandoned. Derived from the one high-water scope
            // rather than a second hand-written predicate that can drift from it.
            ->whereNot(function (Builder $query): void {
                $query->everExhausted();
            });
    }

    protected $fillable = [
        'gym_id',
        'device_id',
        'command_type',
        'command_payload',
        'status',
        'idempotency_key',
        'attempt_count',
        'transient_rearm_count',
        'preserved_evidence_at',
        'leased_at',
        'sent_at',
        'acknowledged_at',
        'expires_at',
        'device_return_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array{
     *     status: 'App\Enums\AdmsCommandStatusEnum',
     *     user_table: 'App\Casts\AdmsUserTableCast',
     *     command_payload: 'array',
     *     attempt_count: 'integer',
     *     max_attempt_count: 'integer',
     *     epoch_credential_user_add_id: 'integer',
     *     leased_at: 'datetime',
     *     sent_at: 'datetime',
     *     acknowledged_at: 'datetime',
     *     expires_at: 'datetime',
     * }
     */
    protected function casts(): array
    {
        return [
            'status' => AdmsCommandStatusEnum::class,
            'user_table' => AdmsUserTableCast::class,
            'command_payload' => 'array',
            'attempt_count' => 'integer',
            'max_attempt_count' => 'integer',
            'transient_rearm_count' => 'integer',
            'preserved_evidence_at' => 'datetime',
            'epoch_credential_user_add_id' => 'integer',
            'leased_at' => 'datetime',
            'sent_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(static function (self $command): void {
            // The device is resolved explicitly rather than through the relation so a
            // queued command can never depend on (or lazily load) the relation later.
            $device = $command->device_id === null
                ? null
                : BiometricDevice::query()->find($command->device_id);

            $command->user_table ??= self::resolveUserTable($device);
            $command->device_serial ??= $device?->device_serial;
        });

        // The high-water mark of `attempt_count`, maintained on every model save so a
        // reset (a fresh epoch, an operator resend, a test seeding a dead-lettered row)
        // cannot destroy the evidence that the cap was reached. Query-builder updates
        // bypass model events, so the two writers that raise the counter do it in SQL.
        static::saving(static function (self $command): void {
            // `getAttribute()` rather than the typed property: a new instance that never set
            // the counter has no attribute yet, and the DB default only applies on insert.
            $attempts = $command->getAttribute('attempt_count');
            $highWater = $command->getAttribute('max_attempt_count');

            $attempts = is_numeric($attempts) ? (int) $attempts : 0;
            $highWater = is_numeric($highWater) ? (int) $highWater : 0;

            if ($attempts > $highWater) {
                $command->max_attempt_count = $attempts;
            }
        });
    }

    /**
     * Resolve the wire schema a command will be delivered under.
     *
     * The schema is snapshotted when the command is queued, not when it is
     * delivered: the per-device override is mutable operator state, and flipping
     * it while a command is leased used to change the format of a command the
     * terminal had already been told to expect. It also keeps the serializer off
     * the `device` relation, so polling a command never issues an extra query.
     */
    public static function resolveUserTable(?BiometricDevice $device): AdmsUserTableEnum
    {
        return AdmsUserTableEnum::resolve(
            $device?->admsUserTableOverride()?->value,
            config('biometrics.adms.user_table')
        );
    }

    /**
     * @return BelongsTo<BiometricDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'device_id');
    }

    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', AdmsCommandStatusEnum::PENDING);
    }

    /**
     * Rows retained as durable proof that a delivered `user_add` reached a deleted
     * terminal.
     *
     * They keep their `SENT` status (the delivery evidence itself), carry the marker so
     * the reclaim sweep and `prunable()` can exclude them without re-deriving intent, and
     * are surfaced to auditors as evidence rather than as in-flight work.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function preservedEvidence(Builder $query): void
    {
        $query->whereNotNull('preserved_evidence_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forDevice(Builder $query, int $deviceId): void
    {
        $query->where('device_id', $deviceId);
    }

    /**
     * Rows whose current delivery budget has been exhausted.
     *
     * This is the operator-facing meaning: the row is at the cap *now*, so it is not
     * currently leasable. A resend resets `attempt_count` and takes the row out of this set,
     * which is what makes the dead-letter label disappear once the operator has re-queued
     * it. Retention must not use this scope — see {@see self::everExhausted()}.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function exhausted(Builder $query): void
    {
        $query->where('attempt_count', '>=', AdmsCommandKey::maxAttempts());
    }

    /**
     * Rows whose delivery budget has ever been exhausted.
     *
     * The high-water mark is authoritative for retention: a re-drive or an operator resend
     * resets the current `attempt_count`, and reading only that would let the durable
     * evidence of an abandoned erasure drop back below the cap and become prunable.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function everExhausted(Builder $query): void
    {
        $query->where('max_attempt_count', '>=', AdmsCommandKey::maxAttempts());
    }

    /**
     * Rows the terminal delivered and rejected at the cap — a true dead letter.
     *
     * A row that only timed out while the terminal was offline is excluded: it was never
     * delivered, so it is a transient outage the reconciler re-drives with a fresh budget,
     * not an erasure the system has abandoned.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function deadLettered(Builder $query): void
    {
        $query->exhausted()
            ->where(function (Builder $query): void {
                $query->whereNull('device_return_code')
                    ->orWhere('device_return_code', '!=', self::TIMEOUT_MAX_ATTEMPTS_CODE);
            });
    }

    /**
     * Build the ADMS protocol command string to send to the device.
     *
     * Format (default): `C:{id}:DATA {ACTION} USERINFO PIN={pin}[...]`.
     * Format (legacy `user` schema): `C:{id}:DATA {ACTION} user Pin={pin}[...]`.
     * Device-level commands (`reboot`, `clear_log`, `INFO`) share one branch and
     * are serialised identically under both schemas.
     *
     * The schema comes from the command row (`user_table`), which is snapshotted
     * when the command is queued. A row queued before the column existed falls
     * back to the configured default.
     *
     * Returns an empty string when a PIN-addressed command carries no usable PIN,
     * or a PIN containing protocol control characters. `user_add`, `user_update`
     * and `user_delete` are all addressed by PIN; transmitting a mangled PIN would
     * address a *different* record on the terminal, so it is never repaired by
     * concatenation.
     */
    public function toAdmsCommandString(): string
    {
        /** @var array<string, mixed> $payload */
        $payload = is_array($this->command_payload) ? $this->command_payload : [];

        $pin = $this->serializedPin($payload);
        $name = $this->serializedName($payload);

        if (! $this->isPinAddressed()) {
            return match ($this->command_type) {
                'reboot' => sprintf('C:%d:REBOOT', $this->id),
                'clear_log' => sprintf('C:%d:CLEAR LOG', $this->id),
                default => sprintf('C:%d:INFO', $this->id),
            };
        }

        if ($pin === '') {
            return '';
        }

        return ($this->user_table ?? AdmsUserTableEnum::resolve(config('biometrics.adms.user_table'))) === AdmsUserTableEnum::UserInfo
            ? $this->userCommandString($pin, $name, ['USERINFO', 'PIN'])
            : $this->userCommandString($pin, $name, ['user', 'Pin']);
    }

    /**
     * The PIN as it may appear on the wire, or '' when it is unsafe to address.
     *
     * @param  array<string, mixed>  $payload
     */
    private function serializedPin(array $payload): string
    {
        $rawPin = $payload['Pin'] ?? null;

        if (! is_string($rawPin) && ! is_numeric($rawPin)) {
            return '';
        }

        $pin = (string) $rawPin;

        return self::pinIsUsable($pin) ? $pin : '';
    }

    /**
     * True when a raw payload PIN can address a terminal record.
     *
     * `Pin` is an identity field: a value that is not a string or number, is
     * empty, or carries a protocol control character would address a different
     * (or no) record, so it is rejected rather than repaired. Stripping the
     * delimiter from `"123\tPri=1"` would produce `123Pri=1` — a *different*
     * terminal record — which is why this predicate is shared with
     * {@see self::leaseNext()} and with the de-provisioner, which must not queue a
     * `user_delete` the lease layer will immediately quarantine.
     */
    public static function pinIsUsable(mixed $rawPin): bool
    {
        if (! is_string($rawPin) && ! is_numeric($rawPin)) {
            return false;
        }

        $pin = (string) $rawPin;

        return $pin !== '' && preg_match('/[\x00-\x1F\x7F]/', $pin) !== 1;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function serializedName(array $payload): string
    {
        $rawName = $payload['Name'] ?? $payload['name'] ?? $payload['NAME'] ?? '';

        if (! is_string($rawName) && ! is_numeric($rawName)) {
            return '';
        }

        // A name is free text, not an identity: protocol delimiters are replaced
        // rather than rejected, keeping the record addressable.
        return preg_replace('/[\x00-\x1F\x7F]/', ' ', (string) $rawName) ?? '';
    }

    /**
     * Build a PIN-addressed command under the given wire schema.
     *
     * The two schemas differ in the master-table token and key casing:
     * - `USERINFO` emits standard uppercase table and PIN key (`USERINFO`, `PIN={pin}`).
     * - `user` emits lowercase table and mixed-case PIN key (`user`, `Pin={pin}`).
     * Both schemas adhere strictly to standard ZKTeco/eSSL protocol field naming:
     * `Name={name}\tPri=0`.
     *
     * `Passwd` and `Card` are deliberately omitted when unconfigured: nothing in the
     * application produces them, and emitting an empty value could clear a credential
     * already enrolled on the physical terminal.
     *
     * @param  array{0: string, 1: string}  $schema  Wire table and PIN key.
     */
    private function userCommandString(string $pin, string $name, array $schema): string
    {
        [$wireTable, $pinKey] = $schema;
        $privilege = 0;

        if ($this->command_type === 'user_delete') {
            return sprintf('C:%d:DATA DELETE %s %s=%s', $this->id, $wireTable, $pinKey, $pin);
        }

        if ($wireTable === 'USERINFO') {
            /**
             * This is not working on device
             * Name not saved on terminal
             * combination: 1
             */
            if (config('biometrics.adms.combination_test') === 1) {
                return sprintf(
                    "C:%d:DATA UPDATE USERINFO PIN=%s\tName=%s\tNAME=%s\tPri=%d\tPrivilege=%d",
                    $this->id,
                    $pin,
                    $name,
                    $name,
                    $privilege,
                    $privilege
                );
            }

            /**
             * This code is also not working
             * Name not saved on terminal
             * combination: 2
             */
            if (config('biometrics.adms.combination_test') === 2) {
                return sprintf(
                    "C:%d:DATA UPDATE USERINFO PIN=%s\tName=%s\tPrivilege=%d\tCard=",
                    $this->id,
                    $pin,
                    $name,
                    $privilege
                );
            }

            /**
             * combination: 3
             */
            if (config('biometrics.adms.combination_test') === 3) {
                return sprintf(
                    "C:%d:DATA UPDATE USERINFO PIN=%s\tName=%s\tPrivilege=%d\tCard=''",
                    $this->id,
                    $pin,
                    $name,
                    $privilege
                );
            }

            /**
             * combination: 4
             */
            if (config('biometrics.adms.combination_test') === 4) {
                return sprintf(
                    "C:%d:DATA UPDATE USERINFO PIN=%s\tName=%s\tPrivilege=%d",
                    $this->id,
                    $pin,
                    $name,
                    $privilege
                );
            }

            /**
             * combination: 5
             */
            if (config('biometrics.adms.combination_test') === 5) {
                return sprintf(
                    "C:%d:DATA UPDATE USERINFO PIN=%s\tName=hiren\tPrivilege=%d",
                    $this->id,
                    $pin,
                    $privilege
                );
            }

            /**
             * combination: 6
             */
            if (config('biometrics.adms.combination_test') === 6) {
                return sprintf(
                    "C:%d:DATA UPDATE USERINFO PIN=%s\tName=%s\tPri=0",
                    $this->id,
                    $pin,
                    $name
                );
            }
        }

        return sprintf(
            "C:%d:DATA UPDATE %s %s=%s\tName=%s\tPri=0",
            $this->id,
            $wireTable,
            $pinKey,
            $pin,
            $name
        );
    }

    /** @var array<int, string> */
    public const PIN_ADDRESSED_COMMAND_TYPES = ['user_add', 'user_update', 'user_delete'];

    /**
     * True when this command targets a specific user record and therefore cannot be expressed
     * without a PIN.
     */
    public function isPinAddressed(): bool
    {
        return in_array($this->command_type, self::PIN_ADDRESSED_COMMAND_TYPES, true);
    }

    /**
     * True when this command can actually be expressed on the wire.
     *
     * Device-level commands (`reboot`, `clear_log`, `INFO`) carry no PIN and are
     * always usable. A PIN-addressed command is usable only when its payload
     * carries a PIN that passes {@see self::pinIsUsable()}; anything else would
     * serialise to an empty string.
     */
    public function hasUsablePin(): bool
    {
        if (! $this->isPinAddressed()) {
            return true;
        }

        /** @var array<string, mixed> $payload */
        $payload = is_array($this->command_payload) ? $this->command_payload : [];

        return self::pinIsUsable($payload['Pin'] ?? null);
    }

    /**
     * Atomically lease the next pending command or expired-lease command for the device.
     *
     * The SQL prefilter drops the common "no PIN at all" shapes, but it cannot
     * express the full predicate: JSON extraction cannot tell a PIN containing a
     * tab from a clean one. The locked batch is therefore re-filtered in PHP with
     * {@see self::hasUsablePin()}, the same predicate the serializer uses, so a
     * command that would serialise to an empty string is never leased and never
     * burns a delivery attempt. Unusable rows are left untouched at PENDING.
     */
    public static function leaseNext(int $deviceId, ?int $leaseSeconds = null): ?self
    {
        $duration = $leaseSeconds ?? config()->integer('biometrics.adms.command_lease_seconds', 600);
        $maxAttempts = AdmsCommandKey::maxAttempts();

        return DB::transaction(function () use ($deviceId, $duration, $maxAttempts) {
            $staleThreshold = now()->subSeconds($duration);

            /** @var Collection<int, self> $candidates */
            $candidates = self::query()
                ->where('device_id', $deviceId)
                ->where(function (Builder $query) use ($staleThreshold, $maxAttempts) {
                    $query->where(function (Builder $pendingQuery) use ($maxAttempts) {
                        $pendingQuery->where('status', AdmsCommandStatusEnum::PENDING)
                            ->where('attempt_count', '<', $maxAttempts);
                    })->orWhere(function (Builder $sub) use ($staleThreshold, $maxAttempts) {
                        $sub->where('status', AdmsCommandStatusEnum::SENT)
                            ->where(function (Builder $nested) use ($staleThreshold) {
                                $nested->whereNull('leased_at')
                                    ->orWhere('leased_at', '<', $staleThreshold)
                                    ->orWhere(function (Builder $exp) {
                                        $exp->whereNotNull('expires_at')
                                            ->where('expires_at', '<', now());
                                    });
                            })
                            ->where('attempt_count', '<', $maxAttempts);
                    });
                })
                ->where(function (Builder $query): void {
                    $query->whereNotIn('command_type', self::PIN_ADDRESSED_COMMAND_TYPES)
                        ->orWhere(function (Builder $sub): void {
                            $sub->whereNotNull('command_payload->Pin')
                                ->where('command_payload->Pin', '!=', '');
                        });
                })
                ->oldest()
                ->limit(50)
                ->lockForUpdate()
                ->get();

            $command = $candidates->first(
                static fn (self $candidate): bool => ! $candidate->isPinAddressed() || $candidate->hasUsablePin()
            );

            if ($command === null) {
                self::quarantineUnaddressable($deviceId, $candidates);

                return null;
            }

            if ($command->status === AdmsCommandStatusEnum::SENT) {
                $command->update([
                    'leased_at' => now(),
                    'expires_at' => now()->addSeconds($duration),
                    'attempt_count' => $command->attempt_count + 1,
                ]);
            } else {
                $command->markSent($duration);
            }

            return $command;
        });
    }

    /**
     * Terminalise the candidates whose PIN can never be expressed on the wire.
     *
     * Leaving them `PENDING` made the 50-candidate lease window a permanent head-of-line
     * block: the oldest malformed rows stayed at the head forever, so every later command
     * — including a `user_delete` — was never leased, and the terminal received `OK` and
     * re-polled while each poll logged them. Quarantining is a one-time transition: the
     * rows are `FAILED` with a return code that names the cause, so they are visible in the
     * ledger, prunable under the normal retention rule, and never re-leased.
     *
     * The caller returns `null` for this poll; the next one selects the following window.
     * Quarantining and continuing in the same call would need an unbounded scan of a queue
     * whose head is arbitrarily long.
     *
     * @param  Collection<int, self>  $candidates
     */
    private static function quarantineUnaddressable(int $deviceId, Collection $candidates): void
    {
        $commandIds = $candidates
            ->filter(static fn (self $candidate): bool => $candidate->isPinAddressed() && ! $candidate->hasUsablePin())
            ->pluck('id')
            ->filter(static fn (mixed $id): bool => is_numeric($id))
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        if ($commandIds === []) {
            return;
        }

        self::query()
            ->whereIn('id', $commandIds)
            ->whereIn('status', [AdmsCommandStatusEnum::PENDING, AdmsCommandStatusEnum::SENT])
            ->update([
                'status' => AdmsCommandStatusEnum::FAILED,
                'device_return_code' => self::UNADDRESSABLE_PIN_CODE,
                // `acknowledged_at` is deliberately left null: the terminal never saw this
                // command, so stamping it would render as a false "Acknowledged At" and
                // become the epoch anchor for a later delete re-drive. Retention falls back
                // to `updated_at` for a FAILED row with no acknowledgement.
                'leased_at' => null,
                'expires_at' => null,
            ]);

        Log::warning('ADMS: quarantined commands whose PIN cannot be addressed; they can never be delivered', [
            'device_id' => $deviceId,
            'command_ids' => $commandIds,
            'device_return_code' => self::UNADDRESSABLE_PIN_CODE,
        ]);
    }

    /**
     * Return a just-leased command to PENDING without consuming a delivery attempt.
     *
     * A command can still turn out to be unexpressible after it was leased (the
     * serializer is the final authority). `attempt_count` counts hardware
     * deliveries only, so the lease-time increment is undone here; it never goes
     * below zero.
     */
    public function releaseUnusableLease(): void
    {
        self::query()
            ->where('id', $this->id)
            ->where('status', AdmsCommandStatusEnum::SENT)
            ->update([
                'status' => AdmsCommandStatusEnum::PENDING,
                'sent_at' => null,
                'leased_at' => null,
                'expires_at' => null,
                // `attempt_count` is UNSIGNED, so the decrement is guarded in SQL rather
                // than written as `attempt_count - 1`: MySQL raises "out of range" for
                // `0 - 1` on an unsigned column. `CASE` is portable, unlike `GREATEST`.
                'attempt_count' => DB::raw('CASE WHEN COALESCE(attempt_count, 0) > 0 THEN attempt_count - 1 ELSE 0 END'),
            ]);

        $this->refresh();
    }

    /**
     * Mark as sent and active lease when dequeued by the device using CAS.
     */
    public function markSent(?int $leaseSeconds = null): bool
    {
        $duration = $leaseSeconds ?? config()->integer('biometrics.adms.command_lease_seconds', 600);

        $affected = self::query()
            ->where('id', $this->id)
            ->where('status', AdmsCommandStatusEnum::PENDING)
            ->update([
                'status' => AdmsCommandStatusEnum::SENT,
                'sent_at' => now(),
                'leased_at' => now(),
                'expires_at' => now()->addSeconds($duration),
                'attempt_count' => $this->attempt_count + 1,
            ]);

        if ($affected > 0) {
            // This was a query-builder write, so the model's `saving` hook did not run.
            // The high-water mark is raised in a separate statement rather than inside the
            // CAS update: an UPDATE's assignments are evaluated against the row being
            // written, so referring to `attempt_count` there is only correct on drivers
            // that apply assignments in order. `whereColumn` is order-independent.
            self::query()
                ->where('id', $this->id)
                ->whereColumn('max_attempt_count', '<', 'attempt_count')
                ->update(['max_attempt_count' => DB::raw('attempt_count')]);

            $this->refresh();

            return true;
        }

        return false;
    }

    /**
     * Mark as acknowledged when device confirms execution.
     * Enforces conditional Compare-And-Set: only SENT commands can transition to terminal states.
     *
     * `attempt_count` counts hardware deliveries only, and is incremented by
     * {@see self::markSent()} and the stale-lease re-lease. A non-zero return code is
     * therefore *not* an extra delivery: the terminal already received and rejected the
     * command, and the reconciler re-drive that follows will consume the next delivery.
     */
    public function markAcknowledged(?string $returnCode = null): bool
    {
        // Guard: only SENT commands can transition to ACKNOWLEDGED or FAILED
        if ($this->status !== AdmsCommandStatusEnum::SENT) {
            return false;
        }

        $code = $returnCode;

        $isDeletionConvergence = $this->isDeletionConvergenceCode($code);
        $isSuccess = ($code !== null && $code === '0') || $isDeletionConvergence;

        if ($this->command_type === 'user_delete' && $code !== null) {
            Log::info('ADMS: user_delete convergence decision', [
                'command_id' => $this->id,
                'device_id' => $this->device_id,
                'gym_id' => $this->gym_id,
                'device_return_code' => substr($code, 0, 255),
                'treated_as_erasure_complete' => $isDeletionConvergence,
                'accepted_codes' => $this->deletionConvergenceCodes(),
            ]);

            // The list ships empty, so a real terminal's "no such user" reply is a FAILED
            // delete: re-driven to the delivery cap and then dead-lettered, with the template
            // never converging to `Deleted`. That is the fail-safe direction — a wrong entry
            // would fabricate an erasure — but it makes the codes a mandatory per-firmware
            // configuration step, so the run that needs them is told about it.
            if (! $isSuccess && $this->deletionConvergenceCodes() === []) {
                Log::warning('ADMS: user_delete rejected and no deletion_convergence_codes are configured; add the codes this firmware returns for an absent user', [
                    'command_id' => $this->id,
                    'device_id' => $this->device_id,
                    'gym_id' => $this->gym_id,
                    'device_return_code' => substr($code, 0, 255),
                ]);
            }
        }

        return DB::transaction(function () use ($isSuccess, $code): bool {
            $updateData = [
                'status' => $isSuccess ? AdmsCommandStatusEnum::ACKNOWLEDGED : AdmsCommandStatusEnum::FAILED,
                'acknowledged_at' => now(),
                'device_return_code' => $code !== null ? substr($code, 0, 255) : 'missing_return',
                'leased_at' => null,
                'expires_at' => null,
            ];

            $affected = self::query()
                ->where('id', $this->id)
                ->where('status', AdmsCommandStatusEnum::SENT)
                ->update($updateData);

            if ($affected === 0) {
                return false;
            }

            $this->refresh();

            if ($isSuccess && $this->command_type === 'user_delete') {
                $this->reconcileUserDelete();
            } elseif ($isSuccess && $this->command_type === 'user_add') {
                $this->reconcileUserAdd();
            }

            return true;
        });
    }

    /**
     * Return codes indicating the user is already absent from the terminal.
     *
     * For `user_delete` only, these converge to success because the terminal no longer
     * holds the credential, satisfying the GDPR Art. 17 erasure obligation. The list is
     * operator-configurable because it is vendor-specific: ZKTeco and eSSL firmware use
     * different conventions for "no such user on device".
     *
     * @return array<int, string>
     */
    protected function deletionConvergenceCodes(): array
    {
        $configured = config('biometrics.adms.deletion_convergence_codes', []);

        if (! is_array($configured)) {
            return [];
        }

        $codes = [];

        foreach ($configured as $code) {
            if (is_scalar($code)) {
                $codes[] = strtolower(trim((string) $code));
            }
        }

        return array_values(array_filter($codes, static fn (string $code): bool => $code !== ''));
    }

    protected function isDeletionConvergenceCode(?string $code): bool
    {
        if ($this->command_type !== 'user_delete' || $code === null) {
            return false;
        }

        $accepted = $this->deletionConvergenceCodes();

        return in_array(strtolower(trim($code)), $accepted, true);
    }

    /**
     * Reconcile biometric templates following a successful hardware user_delete command.
     *
     * A hardware `DATA DELETE` erases the whole credential for a PIN in either
     * schema: the terminal drops the whole record for that PIN, so every template
     * sharing the PIN and awaiting deletion converges to
     * `Deleted` together. A member with both a fingerprint and a face template on one device
     * holds a single PIN and therefore a single hardware credential.
     *
     * Scoped to hardware-backed rows via {@see BiometricTemplate::hardwareBacked()}. A
     * cloud/provider-backed row on the same device shares the PIN but not the credential — the
     * terminal never held it — so a terminal ACK proves nothing about it and must not converge
     * it. Doing so was how a cloud credential whose provider erase was never attempted got
     * marked `Deleted`, leaving the app reporting an erasure that had not happened.
     */
    protected function reconcileUserDelete(): void
    {
        // A null device_id compiles to `IS NULL`, which would match every detached hardware
        // template in the gym that shares the PIN — the same bug fixed in
        // resolveTargetMember(). A command whose terminal was deleted can never be delivered,
        // so it must not converge anything.
        if ($this->device_id === null) {
            return;
        }

        $pinStr = $this->commandPin();

        if ($pinStr === null) {
            return;
        }

        $templates = BiometricTemplate::query()
            ->where('gym_id', $this->gym_id)
            ->hardwareBacked()
            ->where('enrolled_by_device_id', $this->device_id)
            ->where('sync_status', BiometricTemplateSyncStatusEnum::PendingDeletion)
            ->get();

        foreach ($templates as $template) {
            if (! self::templateMatchesPin($template, $pinStr)) {
                continue;
            }

            // A terminal ACK is a confirmed erase: converge the row and clear the durable
            // obligation flag in the same write. Leaving the flag set kept a correctly
            // erased row on the operator surfaces forever with no action that could clear it.
            $template->markErasureDischarged();
        }
    }

    /**
     * Reconcile biometric templates following a successful hardware user_add command.
     *
     * A hardware acknowledgement is terminal confirmation, so a `Pending` placeholder is
     * promoted to `Synced` and activated in the same transaction. Leaving it `Pending` would
     * let the abandoned-placeholder cleanup revoke a credential the terminal is holding.
     *
     * The promotion is conditional on the member still being live. A member archived after the
     * `user_add` was queued must not be handed a live credential back by this reconciliation,
     * so their template is parked at `PendingDeletion` for the erasure reconciler instead of
     * being activated (see MemberArchiveService).
     *
     * For a sibling fan-out command the key carries the *origin* template id, which belongs to
     * a different device's row. The target is therefore resolved by member, device and sync
     * status rather than by that id.
     */
    protected function reconcileUserAdd(): void
    {
        // See reconcileUserDelete(): a null `device_id` is not a scope, it is the absence of
        // one, and `where('enrolled_by_device_id', null)` would match every detached row.
        if ($this->device_id === null) {
            return;
        }

        $pinStr = $this->commandPin();

        if ($pinStr === null) {
            return;
        }

        $key = AdmsCommandKey::tryParse($this->idempotency_key);

        $query = BiometricTemplate::query()
            ->where('gym_id', $this->gym_id)
            ->where('enrolled_by_device_id', $this->device_id)
            ->whereIn('sync_status', BiometricTemplateSyncStatusEnum::awaitingHardwareConfirmationStates());

        if ($key?->isSiblingSync() === true && $key->memberId !== null) {
            $templates = $query
                ->where('gym_member_id', $key->memberId)
                ->where('sync_status', BiometricTemplateSyncStatusEnum::Propagated)
                ->get();
        } elseif ($key !== null && $key->templateId !== null && $key->isSiblingSync() === false) {
            $templates = $query
                ->where('id', $key->templateId)
                ->where('gym_member_id', $key->memberId)
                ->get();
        } elseif ($key !== null && $key->memberId !== null) {
            $templates = $query->where('gym_member_id', $key->memberId)->get();
        } else {
            $templates = $query->get();
        }

        if ($templates->isEmpty()) {
            return;
        }

        // A member archived between queueing a user_add and the terminal
        // acknowledging it must not be handed a live credential back. Refusing
        // the promotion alone would leave the row Pending and inactive while the
        // hardware really is holding the credential — the "door honours a
        // revoked member" state MemberArchiveService forbids — so the row is
        // parked at PendingDeletion for the erasure reconciler instead of being
        // activated.
        $memberIds = self::normalisedIntIds($templates->pluck('gym_member_id'));

        // The predicate is "may not hold a biometric credential", not "is archived": naming the
        // set after the rule it carries keeps the next widening from assuming a different one.
        // `gym_id` is explicit so a member id from another tenant cannot be resolved here.
        $memberIdsBlockedFromHoldingCredentials = self::normalisedIntIds(
            GymMember::query()
                ->where('gym_id', $this->gym_id)
                ->whereIn('id', $memberIds)
                ->get()
                ->reject(fn (GymMember $member): bool => $member->mayHoldBiometricCredential())
                ->pluck('id')
        );

        foreach ($templates as $template) {
            if (! self::templateMatchesPin($template, $pinStr, $key?->memberId)) {
                continue;
            }

            // @phpstan-ignore cast.useless (gym_member_id may hydrate as a string on some drivers; the strict in_array() needs a real int, so the cast is defensive.)
            if (in_array((int) $template->gym_member_id, $memberIdsBlockedFromHoldingCredentials, true)) {
                $template->update([
                    'is_active' => false,
                    'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
                ]);

                continue;
            }

            $updates = [
                'enrolled_at' => $template->enrolled_at ?? now(),
                'hardware_confirmed_at' => now(),
            ];

            if ($template->sync_status === BiometricTemplateSyncStatusEnum::Pending) {
                $updates['sync_status'] = BiometricTemplateSyncStatusEnum::Synced;
                $updates['is_active'] = true;
            }

            $template->update($updates);
        }
    }

    /**
     * Normalise id values to a list of ints for strict comparison. Some drivers hydrate integer
     * columns as strings; a non-numeric value becomes 0, which can never match a persisted id.
     *
     * @param  Collection<int, mixed>  $ids
     * @return array<int, int>
     */
    private static function normalisedIntIds(Collection $ids): array
    {
        return $ids->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Read the hardware PIN from the command payload.
     */
    protected function commandPin(): ?string
    {
        /** @var array<string, mixed> $payload */
        $payload = is_array($this->command_payload) ? $this->command_payload : [];
        $pin = $payload['Pin'] ?? null;

        if (! is_scalar($pin)) {
            return null;
        }

        $pinStr = (string) $pin;

        return $pinStr === '' ? null : $pinStr;
    }

    /**
     * Check if a template matches a given command PIN, optionally scoped to expected member ID.
     */
    public static function templateMatchesPin(BiometricTemplate $template, string $pinStr, ?int $expectedMemberId = null): bool
    {
        if ($expectedMemberId !== null && $template->gym_member_id !== $expectedMemberId) {
            return false;
        }

        return $template->resolvedCommandPin() === $pinStr
            || (string) $template->template_ref === $pinStr
            || (is_numeric($pinStr) && $template->gym_member_id === (int) $pinStr)
            || in_array($pinStr, $template->getCacheableReferences(), true);
    }

    /**
     * Mark command as cancelled and clear idempotency key so retry can succeed.
     * Allows cancelling commands in PENDING or SENT status.
     */
    public function markCancelled(): bool
    {
        if (! in_array($this->status, [AdmsCommandStatusEnum::PENDING, AdmsCommandStatusEnum::SENT], true)) {
            return false;
        }

        return (bool) self::query()
            ->where('id', $this->id)
            ->whereIn('status', [AdmsCommandStatusEnum::PENDING, AdmsCommandStatusEnum::SENT])
            ->update([
                'status' => AdmsCommandStatusEnum::CANCELLED,
                'idempotency_key' => null,
                'leased_at' => null,
                'expires_at' => null,
            ]);
    }

    /**
     * Return a command to the queue as an audited operator action.
     *
     * The reset and the audit entry share one transaction and one sink assertion: an audit
     * failure rolls the reset back, so the ledger never says a command was re-queued without
     * saying who did it. `max_attempt_count` is deliberately not reset — it is the durable
     * retention evidence that the row was ever dead-lettered.
     *
     * This is the single writer used by the ledger resend action and the template
     * "Retry Erasure" action, so the two cannot diverge on what a resend means.
     */
    public function requeueForOperator(?User $actor = null): bool
    {
        $previousAttempts = $this->attempt_count;
        $previousReturnCode = $this->device_return_code;
        $highWater = $this->max_attempt_count;

        return DB::transaction(function () use ($actor, $previousAttempts, $previousReturnCode, $highWater): bool {
            $this->assertAuditSinkIsTransactional();

            // Compare-and-set on the state the operator saw. A concurrent terminal ACK
            // between the read and this write moves the row to ACKNOWLEDGED, which is not
            // in the set, so the update affects zero rows and the evidence that the erase
            // happened is not overwritten with PENDING.
            $affected = self::query()
                ->whereKey($this->getKey())
                ->whereIn('status', [AdmsCommandStatusEnum::FAILED, AdmsCommandStatusEnum::SENT])
                ->update([
                    'status' => AdmsCommandStatusEnum::PENDING,
                    'attempt_count' => 0,
                    // An operator re-arm is an explicit override of the transient bound:
                    // the next timeout starts the budget again rather than immediately
                    // re-parking the row.
                    'transient_rearm_count' => 0,
                    'sent_at' => null,
                    'leased_at' => null,
                    'expires_at' => null,
                    'acknowledged_at' => null,
                    'device_return_code' => null,
                ]);

            if ($affected !== 1) {
                return false;
            }

            $this->refresh();

            $activityLogger = activity('adms')
                ->performedOn($this)
                ->withProperties([
                    'gym_id' => $this->gym_id,
                    'device_id' => $this->device_id,
                    'actor_id' => $actor?->getKey(),
                    'previous_attempt_count' => $previousAttempts,
                    'max_attempt_count' => $highWater,
                    'previous_device_return_code' => $previousReturnCode,
                ]);

            if ($actor !== null) {
                $activityLogger->causedBy($actor);
            }

            if ($activityLogger->log('ADMS command re-queued by operator') === null) {
                throw new RuntimeException(
                    'The command was not re-queued because its audit entry could not be written.'
                );
            }

            return true;
        });
    }

    /**
     * Cancel pending or in-flight user_delete commands on a device for given pins.
     *
     * @param  array<int, string>  $pins
     */
    public static function cancelDeleteCommands(int $deviceId, array $pins): int
    {
        if (blank($pins)) {
            return 0;
        }

        return self::query()
            ->where('device_id', $deviceId)
            ->where('command_type', 'user_delete')
            ->whereIn('status', [AdmsCommandStatusEnum::PENDING, AdmsCommandStatusEnum::SENT])
            ->where(function (Builder $q) use ($pins) {
                foreach ($pins as $pin) {
                    $q->orWhere('command_payload->Pin', $pin);
                }
            })
            ->update([
                'status' => AdmsCommandStatusEnum::CANCELLED,
                'idempotency_key' => null,
                'leased_at' => null,
                'expires_at' => null,
            ]);
    }

    /**
     * Cancel pending or in-flight commands on a device for a given pin,
     * optionally preserving a specific in-flight idempotency key.
     *
     * Handles SQL NULL comparison correctly via whereNull()->orWhere().
     */
    public static function cancelInFlightFor(
        int $deviceId,
        string $pin,
        ?string $exceptIdempotencyKey = null,
        string $commandType = 'user_add'
    ): int {
        return self::query()
            ->where('device_id', $deviceId)
            ->where('command_type', $commandType)
            ->whereIn('status', [AdmsCommandStatusEnum::PENDING, AdmsCommandStatusEnum::SENT])
            ->where('command_payload->Pin', $pin)
            ->when($exceptIdempotencyKey !== null, function (Builder $q) use ($exceptIdempotencyKey): void {
                $q->where(function (Builder $sub) use ($exceptIdempotencyKey): void {
                    $sub->whereNull('idempotency_key')
                        ->orWhere('idempotency_key', '!=', $exceptIdempotencyKey);
                });
            })
            ->update([
                'status' => AdmsCommandStatusEnum::CANCELLED,
                'idempotency_key' => null,
                'leased_at' => null,
                'expires_at' => null,
            ]);
    }

    /**
     * Generate canonical idempotency key for user_add commands across lifecycle stages.
     */
    public static function idempotencyKeyFor(
        int $gymId,
        int $deviceId,
        int|string $memberId,
        ?int $templateId = null,
        ?string $stage = null
    ): string {
        return AdmsCommandKey::buildUserAdd($gymId, $deviceId, $memberId, $templateId, $stage);
    }

    /**
     * Generate canonical idempotency key for user_delete commands.
     *
     * PIN-scoped: ADMS erases a whole PIN, so the PIN is the command's identity. Scoping the
     * key to a template would let two templates sharing a PIN each insert a command for the
     * same terminal record.
     */
    public static function deleteIdempotencyKeyFor(int $deviceId, string $pin): string
    {
        return AdmsCommandKey::buildUserDelete($deviceId, $pin);
    }

    /**
     * Resolve the gym member a provisioning command targets, for consent enforcement.
     *
     * The ADMS payload carries only a PIN. For device-native enrolment the PIN is the member
     * id; for template-scoped refs it is the stored `template_ref`, which resolves back
     * through `biometric_templates`. Returns null when the member cannot be determined, in
     * which case callers must not assume consent exists.
     */
    public function resolveTargetMember(): ?GymMember
    {
        if (! $this->isPinAddressed()) {
            return null;
        }

        /** @var array<string, mixed> $payload */
        $payload = is_array($this->command_payload) ? $this->command_payload : [];
        $pin = $payload['Pin'] ?? null;

        if (! is_scalar($pin)) {
            return null;
        }

        $pinStr = (string) $pin;

        if ($pinStr === '') {
            return null;
        }

        if (ctype_digit($pinStr)) {
            $member = GymMember::query()
                ->where('gym_id', $this->gym_id)
                ->find((int) $pinStr);

            if ($member !== null) {
                return $member;
            }
        }

        // A null device cannot scope the template lookup: `where('enrolled_by_device_id', null)`
        // compiles to `IS NULL`, which matches every unbound template in the gym and could
        // resolve another member's row. The digit lookup above is already safe, so give up here
        // rather than guess.
        if ($this->device_id === null) {
            return null;
        }

        $template = BiometricTemplate::query()
            ->where('gym_id', $this->gym_id)
            ->where('enrolled_by_device_id', $this->device_id)
            ->whereIn('sync_status', BiometricTemplateSyncStatusEnum::liveOrPendingCredentialStates())
            ->get()
            ->first(fn (BiometricTemplate $candidate): bool => $candidate->resolvedCommandPin() === $pinStr);

        return $template?->gymMember;
    }

    /**
     * True when re-queueing this command is permitted by the biometric consent gate.
     *
     * A `user_add`/`user_update` pushes a member's PIN and name back onto a terminal, so it
     * must not be re-queued once consent is absent or revoked (ADR-003 / OpenSpec §4).
     */
    public function isConsentPermitted(): bool
    {
        if (! in_array($this->command_type, ['user_add', 'user_update'], true)) {
            return true;
        }

        $member = $this->resolveTargetMember();

        if ($member === null) {
            return false;
        }

        return $member->hasActiveBiometricConsent(fresh: true);
    }
}
