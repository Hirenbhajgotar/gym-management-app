<?php

namespace App\Models;

use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricDoorControlType;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Database\Factories\BiometricDeviceFactory;
use App\Enums\BiometricDevicePurpose;
use App\Observers\BiometricDeviceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

/**
 * @property int $id
 * @property int $gym_id
 * @property string $device_name
 * @property string $device_type
 * @property string $provider_type
 * @property array<array-key, mixed>|null $provider_config
 * @property BiometricDeviceStatusEnum $status
 * @property BiometricDevicePurpose $purpose
 * @property BiometricDoorControlType|null $door_control_type
 * @property string|null $door_controller_url
 * @property string|null $door_controller_token
 * @property int $unlock_duration_seconds
 * @property string|null $door_zone
 * @property string $connection_mode
 * @property int $poll_interval_seconds
 * @property Carbon|null $last_polled_at
 * @property int|null $assigned_staff_id
 * @property bool $offline_capable
 * @property bool $supports_multi_modal
 * @property Carbon|null $last_heartbeat_at
 * @property string|null $firmware_version
 * @property string|null $device_token
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
 * @method static Builder<static>|BiometricDevice whereConnectionMode($value)
 * @method static Builder<static>|BiometricDevice whereCreatedAt($value)
 * @method static Builder<static>|BiometricDevice whereCreatedBy($value)
 * @method static Builder<static>|BiometricDevice whereDeviceName($value)
 * @method static Builder<static>|BiometricDevice whereDeviceToken($value)
 * @method static Builder<static>|BiometricDevice whereDeviceType($value)
 * @method static Builder<static>|BiometricDevice whereDoorControlType($value)
 * @method static Builder<static>|BiometricDevice whereDoorControllerToken($value)
 * @method static Builder<static>|BiometricDevice whereDoorControllerUrl($value)
 * @method static Builder<static>|BiometricDevice whereDoorZone($value)
 * @method static Builder<static>|BiometricDevice whereFirmwareVersion($value)
 * @method static Builder<static>|BiometricDevice whereGymId($value)
 * @method static Builder<static>|BiometricDevice whereId($value)
 * @method static Builder<static>|BiometricDevice whereLastHeartbeatAt($value)
 * @method static Builder<static>|BiometricDevice whereLastPolledAt($value)
 * @method static Builder<static>|BiometricDevice whereOfflineCapable($value)
 * @method static Builder<static>|BiometricDevice wherePollIntervalSeconds($value)
 * @method static Builder<static>|BiometricDevice whereProviderConfig($value)
 * @method static Builder<static>|BiometricDevice whereProviderType($value)
 * @method static Builder<static>|BiometricDevice wherePurpose($value)
 * @method static Builder<static>|BiometricDevice whereStatus($value)
 * @method static Builder<static>|BiometricDevice whereSupportsMultiModal($value)
 * @method static Builder<static>|BiometricDevice whereUnlockDurationSeconds($value)
 * @method static Builder<static>|BiometricDevice whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy([BiometricDeviceObserver::class])]
class BiometricDevice extends Model
{
    use HasFactory;

    public const NAVIGATION_BADGE_CACHE_KEY = 'nav_badge:biometric_device:tenant_id:';

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
            'status' => BiometricDeviceStatusEnum::class,
            // Door control casts
            'door_controller_token' => 'encrypted',
            'unlock_duration_seconds' => 'integer',
            'poll_interval_seconds' => 'integer',
            'last_polled_at' => 'datetime',
            'purpose' => BiometricDevicePurpose::class,
            'door_control_type' => BiometricDoorControlType::class,
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
        return $this->door_control_type === BiometricDoorControlType::Api
            && filled($this->door_controller_url);
    }
}
