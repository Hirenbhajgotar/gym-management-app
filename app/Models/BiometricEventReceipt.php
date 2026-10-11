<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BiometricReceiptStatusEnum;
use Database\Factories\BiometricEventReceiptFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $gym_id
 * @property int $device_id
 * @property string $event_fingerprint
 * @property BiometricReceiptStatusEnum $status
 * @property int|null $attendance_id
 * @property array<string, mixed>|null $payload_summary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Gym $gym
 * @property-read BiometricDevice $device
 * @property-read Attendances|null $attendance
 *
 * @use HasFactory<BiometricEventReceiptFactory>
 */
class BiometricEventReceipt extends Model
{
    /** @use HasFactory<BiometricEventReceiptFactory> */
    use HasFactory;

    use Prunable;

    /**
     * Get the prunable model query.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $retentionDays = config()->integer('biometrics.adms.receipt_retention_days', 30);

        return static::query()->where('created_at', '<=', now()->subDays($retentionDays));
    }

    protected $fillable = [
        'gym_id',
        'device_id',
        'event_fingerprint',
        'status',
        'attendance_id',
        'payload_summary',
    ];

    protected function casts(): array
    {
        return [
            'status' => BiometricReceiptStatusEnum::class,
            'payload_summary' => 'array',
        ];
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
    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'device_id');
    }

    /**
     * @return BelongsTo<Attendances, $this>
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendances::class, 'attendance_id');
    }
}
