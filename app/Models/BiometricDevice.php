<?php

namespace App\Models;

use App\Enums\BiometricDevicePurpose;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class BiometricDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'device_name',
        'device_type',
        'provider_type',
        'provider_config',
        'status',
        'assigned_staff_id',
        'offline_capable',
        'supports_multi_modal',
        'last_heartbeat_at',
        'firmware_version',
        'device_token',
        'created_by',
        // Door control fields
        'purpose',
        'door_control_type',
        'door_controller_url',
        'door_controller_token',
        'unlock_duration_seconds',
        'door_zone',
        'connection_mode',
        'poll_interval_seconds',
        'last_polled_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_config' => 'encrypted:array',
            'offline_capable' => 'boolean',
            'supports_multi_modal' => 'boolean',
            'last_heartbeat_at' => 'datetime',
            'device_token' => 'hashed',
            'status' => \App\Enums\BiometricDeviceStatusEnum::class,
            // Door control casts
            'door_controller_token' => 'encrypted',
            'unlock_duration_seconds' => 'integer',
            'poll_interval_seconds' => 'integer',
            'last_polled_at' => 'datetime',
            'purpose' => \App\Enums\BiometricDevicePurpose::class,
            'door_control_type' => \App\Enums\BiometricDoorControlType::class,
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(BiometricTemplate::class, 'enrolled_by_device_id');
    }

    public function doorEvents(): HasMany
    {
        return $this->hasMany(DoorEvent::class, 'device_id');
    }

    #[Scope]
    protected function active($query): void
    {
        $query->where('status', 'active');
    }

    #[Scope]
    protected function forGym($query, int $gymId): void
    {
        $query->where('gym_id', $gymId);
    }

    public function verifyToken(string $plainToken): bool
    {
        return $this->device_token !== null && Hash::check($plainToken, $this->device_token);
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
        ]);
    }

    public function shouldCreateAttendance(): bool
    {
        return in_array($this->purpose, [
            BiometricDevicePurpose::AttendanceOnly,
            BiometricDevicePurpose::DoorAccess,
        ]);
    }

    public function hasDoorApiControl(): bool
    {
        return $this->door_control_type === \App\Enums\BiometricDoorControlType::Api
            && ! empty($this->door_controller_url);
    }
}
