<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $gym_id
 * @property int|null $device_id
 * @property string|null $device_serial
 * @property int|null $gym_member_id
 * @property int|null $attendance_id
 * @property string $event_type
 * @property string $trigger_source
 * @property string $door_status
 * @property string|null $denial_reason
 * @property int|null $unlock_duration_seconds
 * @property Carbon|null $unlocked_at
 * @property string|null $ip_address
 * @property array<array-key, mixed>|null $raw_response
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Attendances|null $attendance
 * @property-read BiometricDevice $device
 * @property-read Gym $gym
 * @property-read GymMember|null $gymMember
 *
 * @method static Builder<static>|DoorEvent newModelQuery()
 * @method static Builder<static>|DoorEvent newQuery()
 * @method static Builder<static>|DoorEvent query()
 * @method static Builder<static>|DoorEvent whereAttendanceId($value)
 * @method static Builder<static>|DoorEvent whereCreatedAt($value)
 * @method static Builder<static>|DoorEvent whereDenialReason($value)
 * @method static Builder<static>|DoorEvent whereDeviceId($value)
 * @method static Builder<static>|DoorEvent whereDoorStatus($value)
 * @method static Builder<static>|DoorEvent whereEventType($value)
 * @method static Builder<static>|DoorEvent whereGymId($value)
 * @method static Builder<static>|DoorEvent whereGymMemberId($value)
 * @method static Builder<static>|DoorEvent whereId($value)
 * @method static Builder<static>|DoorEvent whereIpAddress($value)
 * @method static Builder<static>|DoorEvent whereRawResponse($value)
 * @method static Builder<static>|DoorEvent whereTriggerSource($value)
 * @method static Builder<static>|DoorEvent whereUnlockDurationSeconds($value)
 * @method static Builder<static>|DoorEvent whereUnlockedAt($value)
 * @method static Builder<static>|DoorEvent whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class DoorEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'device_id',
        'device_serial',
        'gym_member_id',
        'attendance_id',
        'event_type',
        'trigger_source',
        'denial_reason',
        'door_status',
        'unlock_duration_seconds',
        'unlocked_at',
        'ip_address',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'unlocked_at' => 'datetime',
            'raw_response' => 'array',
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
     * @return BelongsTo<GymMember, $this>
     */
    public function gymMember(): BelongsTo
    {
        return $this->belongsTo(GymMember::class);
    }

    /**
     * @return BelongsTo<Attendances, $this>
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendances::class, 'attendance_id');
    }

    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }
}
