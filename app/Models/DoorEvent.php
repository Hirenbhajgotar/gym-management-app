<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gym_id
 * @property int $device_id
 * @property int|null $gym_member_id
 * @property int|null $attendance_id
 * @property string $event_type
 * @property string $trigger_source
 * @property string $door_status
 * @property string|null $denial_reason
 * @property int|null $unlock_duration_seconds
 * @property \Illuminate\Support\Carbon|null $unlocked_at
 * @property string|null $ip_address
 * @property array<array-key, mixed>|null $raw_response
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Attendances|null $attendance
 * @property-read \App\Models\BiometricDevice $device
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\GymMember|null $gymMember
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DoorEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DoorEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DoorEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DoorEvent whereAttendanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DoorEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DoorEvent whereDenialReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DoorEvent whereDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DoorEvent whereDoorStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DoorEvent whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DoorEvent whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DoorEvent whereGymMemberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DoorEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DoorEvent whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DoorEvent whereRawResponse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DoorEvent whereTriggerSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DoorEvent whereUnlockDurationSeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DoorEvent whereUnlockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DoorEvent whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class DoorEvent extends Model
{
    protected $fillable = [
        'gym_id',
        'device_id',
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

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'device_id');
    }

    public function gymMember(): BelongsTo
    {
        return $this->belongsTo(GymMember::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendances::class, 'attendance_id');
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }
}
