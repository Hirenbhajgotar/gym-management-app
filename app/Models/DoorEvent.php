<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
