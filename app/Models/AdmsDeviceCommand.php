<?php

declare(strict_types=1);

namespace App\Models;

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
     */
    public function toAdmsCommandString(): string
    {
        /** @var array<string, mixed> $payload */
        $payload = is_array($this->command_payload) ? $this->command_payload : [];
        $rawPin = $payload['Pin'] ?? '';
        $pin = (is_string($rawPin) || is_numeric($rawPin)) ? (string) $rawPin : '';
        $rawName = $payload['Name'] ?? '';
        $name = (is_string($rawName) || is_numeric($rawName)) ? (string) $rawName : '';

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

    /**
     * Atomically lease the next pending command or expired-lease command for the device.
     */
    public static function leaseNext(int $deviceId, ?int $leaseSeconds = null): ?self
    {
        $duration = $leaseSeconds ?? config()->integer('biometrics.adms.command_lease_seconds', 600);

        return DB::transaction(function () use ($deviceId, $duration) {
            $staleThreshold = now()->subSeconds($duration);

            /** @var self|null $command */
            $command = self::query()
                ->where('device_id', $deviceId)
                ->where(function (Builder $query) use ($staleThreshold) {
                    $query->where(function (Builder $pendingQuery) {
                        $pendingQuery->where('status', AdmsCommandStatusEnum::PENDING)
                            ->where('attempt_count', '<', 3);
                    })->orWhere(function (Builder $sub) use ($staleThreshold) {
                        $sub->where('status', AdmsCommandStatusEnum::SENT)
                            ->where(function (Builder $nested) use ($staleThreshold) {
                                $nested->whereNull('leased_at')
                                    ->orWhere('leased_at', '<', $staleThreshold)
                                    ->orWhere(function (Builder $exp) {
                                        $exp->whereNotNull('expires_at')
                                            ->where('expires_at', '<', now());
                                    });
                            })
                            ->where('attempt_count', '<', 3);
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
     */
    public function markAcknowledged(?string $returnCode = null): bool
    {
        // Guard: only SENT commands can transition to ACKNOWLEDGED or FAILED
        if ($this->status !== AdmsCommandStatusEnum::SENT) {
            return false;
        }

        $code = $returnCode;
        $isSuccess = ($code !== null && $code === '0');

        return DB::transaction(function () use ($code, $isSuccess): bool {
            $affected = self::query()
                ->where('id', $this->id)
                ->where('status', AdmsCommandStatusEnum::SENT)
                ->update([
                    'status' => $isSuccess ? AdmsCommandStatusEnum::ACKNOWLEDGED : AdmsCommandStatusEnum::FAILED,
                    'acknowledged_at' => now(),
                    'device_return_code' => $code ?? 'missing_return',
                    'leased_at' => null,
                    'expires_at' => null,
                    'idempotency_key' => null,
                ]);

            if ($affected === 0) {
                return false;
            }

            $this->refresh();

            if ($isSuccess && $this->command_type === 'user_delete') {
                $this->reconcileUserDelete();
            }

            return true;
        });
    }

    /**
     * Reconcile biometric templates following a successful hardware user_delete command.
     */
    protected function reconcileUserDelete(): void
    {
        /** @var array<string, mixed> $payload */
        $payload = is_array($this->command_payload) ? $this->command_payload : [];
        $pin = $payload['Pin'] ?? null;

        if ($pin === null) {
            return;
        }

        $pinStr = is_scalar($pin) ? (string) $pin : '';
        if ($pinStr === '') {
            return;
        }
        $templates = BiometricTemplate::query()
            ->where('enrolled_by_device_id', $this->device_id)
            ->where('sync_status', BiometricTemplateSyncStatusEnum::PendingDeletion)
            ->get();

        foreach ($templates as $template) {
            if ((string) $template->template_ref === $pinStr
                || (is_numeric($pin) && $template->gym_member_id === (int) $pin)
                || in_array($pinStr, $template->getCacheableReferences(), true)
            ) {
                $template->update([
                    'sync_status' => BiometricTemplateSyncStatusEnum::Deleted,
                    'is_active' => false,
                ]);
            }
        }
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
}
