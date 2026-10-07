<?php

declare(strict_types=1);

namespace App\Models;

use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use Database\Factories\AdmsDeviceCommandFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @property int $id
 * @property int $gym_id
 * @property int $device_id
 * @property string $command_type
 * @property array<string, mixed>|null $command_payload
 * @property AdmsCommandStatusEnum $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $acknowledged_at
 * @property string|null $device_return_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BiometricDevice $device
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
    /** @use HasFactory<AdmsDeviceCommandFactory> */
    use HasFactory;

    use Prunable;

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
            ->where(function (Builder $query) use ($cutoff) {
                $query->where('acknowledged_at', '<=', $cutoff)
                    ->orWhere(function (Builder $sub) use ($cutoff) {
                        $sub->whereNull('acknowledged_at')
                            ->where('updated_at', '<=', $cutoff);
                    });
            })
            ->where(function (Builder $query): void {
                // Retain exhausted rows: they are the only durable evidence that a GDPR
                // erasure was attempted and abandoned, and the retry counter must not reset.
                $query->where('attempt_count', '<', AdmsCommandKey::maxAttempts())
                    ->orWhereNull('attempt_count');
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
     *     command_payload: 'array',
     *     attempt_count: 'integer',
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
            'command_payload' => 'array',
            'attempt_count' => 'integer',
            'leased_at' => 'datetime',
            'sent_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
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
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forDevice(Builder $query, int $deviceId): void
    {
        $query->where('device_id', $deviceId);
    }

    /**
     * Build the ADMS protocol command string to send to the device.
     *
     * Format: C:{id}:DATA {ACTION} user {params}
     *
     * Returns an empty string when a PIN-addressed command has no usable PIN. `user_add`,
     * `user_update` and `user_delete` are all addressed by PIN, so one without a PIN cannot be
     * expressed and must never be transmitted: sending it would address an unintended record
     * on the terminal. Device-level commands (`reboot`, `clear_log`) carry no PIN and are
     * unaffected.
     */
    public function toAdmsCommandString(): string
    {
        /** @var array<string, mixed> $payload */
        $payload = is_array($this->command_payload) ? $this->command_payload : [];
        $rawPin = $payload['Pin'] ?? '';
        $pin = (is_string($rawPin) || is_numeric($rawPin)) ? (string) $rawPin : '';
        $rawName = $payload['Name'] ?? '';
        $name = (is_string($rawName) || is_numeric($rawName)) ? (string) $rawName : '';

        // Strip protocol delimiters (\t, \r, \n) to prevent command injection
        $pin = preg_replace('/[\r\n\t]/', '', $pin) ?? '';
        $name = preg_replace('/[\r\n\t]/', ' ', $name) ?? '';

        $isPinAddressed = $this->isPinAddressed();

        if ($isPinAddressed && $pin === '') {
            return '';
        }

        return match ($this->command_type) {
            'user_delete' => sprintf('C:%d:DATA DELETE user Pin=%s', $this->id, $pin),
            'user_add', 'user_update' => sprintf(
                "C:%d:DATA UPDATE user Pin=%s\tName=%s\tPri=0",
                $this->id,
                $pin,
                $name
            ),
            'reboot' => sprintf('C:%d:REBOOT', $this->id),
            'clear_log' => sprintf('C:%d:CLEAR LOG', $this->id),
            default => sprintf('C:%d:INFO', $this->id),
        };
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
     * Atomically lease the next pending command or expired-lease command for the device.
     *
     * A PIN-addressed command with no usable PIN is never leased: `toAdmsCommandString()`
     * returns an empty string for it, so leasing would burn a delivery attempt on a command
     * that can never be transmitted.
     */
    public static function leaseNext(int $deviceId, ?int $leaseSeconds = null): ?self
    {
        $duration = $leaseSeconds ?? config()->integer('biometrics.adms.command_lease_seconds', 600);
        $maxAttempts = AdmsCommandKey::maxAttempts();

        return DB::transaction(function () use ($deviceId, $duration, $maxAttempts) {
            $staleThreshold = now()->subSeconds($duration);

            /** @var self|null $command */
            $command = self::query()
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
                ->lockForUpdate()
                ->first();

            if ($command) {
                if ($command->status === AdmsCommandStatusEnum::SENT) {
                    $command->update([
                        'leased_at' => now(),
                        'expires_at' => now()->addSeconds($duration),
                        'attempt_count' => $command->attempt_count + 1,
                    ]);
                } else {
                    $command->markSent($duration);
                }
            }

            return $command;
        });
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
     * `DATA DELETE user Pin=N` is user-granular: the terminal drops the whole credential for
     * that PIN, so every template sharing the PIN and awaiting deletion converges to
     * `Deleted` together. A member with both a fingerprint and a face template on one device
     * holds a single PIN and therefore a single hardware credential.
     */
    protected function reconcileUserDelete(): void
    {
        $pinStr = $this->commandPin();

        if ($pinStr === null) {
            return;
        }

        $templates = BiometricTemplate::query()
            ->where('gym_id', $this->gym_id)
            ->where('enrolled_by_device_id', $this->device_id)
            ->where('sync_status', BiometricTemplateSyncStatusEnum::PendingDeletion)
            ->get();

        foreach ($templates as $template) {
            if (! self::templateMatchesPin($template, $pinStr)) {
                continue;
            }

            $template->update([
                'sync_status' => BiometricTemplateSyncStatusEnum::Deleted,
                'is_active' => false,
            ]);
        }
    }

    /**
     * Reconcile biometric templates following a successful hardware user_add command.
     *
     * A hardware acknowledgement is terminal confirmation, so a `Pending` placeholder is
     * promoted to `Synced` and activated in the same transaction. Leaving it `Pending` would
     * let the abandoned-placeholder cleanup revoke a credential the terminal is holding.
     *
     * For a sibling fan-out command the key carries the *origin* template id, which belongs to
     * a different device's row. The target is therefore resolved by member, device and sync
     * status rather than by that id.
     */
    protected function reconcileUserAdd(): void
    {
        $pinStr = $this->commandPin();

        if ($pinStr === null) {
            return;
        }

        $key = AdmsCommandKey::tryParse($this->idempotency_key);

        $query = BiometricTemplate::query()
            ->where('gym_id', $this->gym_id)
            ->where('enrolled_by_device_id', $this->device_id)
            ->whereIn('sync_status', [
                BiometricTemplateSyncStatusEnum::Pending,
                BiometricTemplateSyncStatusEnum::Propagated,
            ]);

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

        foreach ($templates as $template) {
            if (! self::templateMatchesPin($template, $pinStr, $key?->memberId)) {
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

        $template = BiometricTemplate::query()
            ->where('gym_id', $this->gym_id)
            ->where('enrolled_by_device_id', $this->device_id)
            ->whereIn('sync_status', [
                BiometricTemplateSyncStatusEnum::Pending,
                BiometricTemplateSyncStatusEnum::Synced,
                BiometricTemplateSyncStatusEnum::Propagated,
                BiometricTemplateSyncStatusEnum::PendingDeletion,
            ])
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
