<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\BiometricTypeEnum;
use App\Observers\AttendanceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $attendanceable_type
 * @property int $attendanceable_id
 * @property int $gym_id
 * @property \Illuminate\Support\Carbon|null $check_in_time
 * @property \Illuminate\Support\Carbon|null $check_out_time
 * @property AttendanceStatus|null $status
 * @property string|null $note
 * @property int|null $device_id
 * @property string|null $offline_id
 * @property string|null $check_in_method
 * @property BiometricTypeEnum|null $biometric_type
 * @property string|null $provider_type
 * @property float|null $confidence_score
 * @property float|null $liveness_score
 * @property float|null $fusion_score
 * @property string|null $check_in_result
 * @property string|null $denial_reason
 * @property string|null $door_status
 * @property string|null $door_denial_reason
 * @property \Illuminate\Support\Carbon|null $door_unlocked_at
 * @property \Illuminate\Support\Carbon|null $synced_at
 * @property string|null $check_in_ip
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent $attendanceable
 * @property-read \App\Models\BiometricDevice|null $device
 * @property-read \App\Models\DoorEvent|null $doorEvent
 * @property-read mixed $duration
 * @property-read mixed $duration_in_minutes
 * @property-read \App\Models\Gym $gym
 * @property-read mixed $is_active
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances absent()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances biometric()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances byDate($date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances byGym($gymId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances completed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereAttendanceableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereAttendanceableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereBiometricType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereCheckInIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereCheckInMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereCheckInResult($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereCheckInTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereCheckOutTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereConfidenceScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereDenialReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereDoorDenialReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereDoorStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereDoorUnlockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereFusionScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereLivenessScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereOfflineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereProviderType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereSyncedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendances whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperAttendances
 */
#[ObservedBy([AttendanceObserver::class])]
class Attendances extends Model
{
    protected $fillable = [
        'attendanceable_id',
        'attendanceable_type',
        'gym_id',
        'check_in_time',
        'check_out_time',
        'status',
        'note',
        'device_id',
        'check_in_method',
        'biometric_type',
        'provider_type',
        'confidence_score',
        'liveness_score',
        'fusion_score',
        'check_in_result',
        'denial_reason',
        'synced_at',
        'check_in_ip',
        // Door control fields
        'door_status',
        'door_denial_reason',
        'door_unlocked_at',
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'status' => AttendanceStatus::class,
        'confidence_score' => 'float',
        'liveness_score' => 'float',
        'fusion_score' => 'float',
        'synced_at' => 'datetime',
        'biometric_type' => BiometricTypeEnum::class,
        // Door control casts
        'door_unlocked_at' => 'datetime',
    ];

    public function attendanceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'device_id');
    }

    public function doorEvent(): HasOne
    {
        return $this->hasOne(DoorEvent::class, 'attendance_id');
    }

    protected function duration(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (! $this->check_out_time) {
                    return null;
                }

                $diff = $this->check_in_time->diff($this->check_out_time);

                return $diff->format('%H:%I:%S');
            },
        );
    }

    protected function durationInMinutes(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (! $this->check_in_time || ! $this->check_out_time) {
                    return null;
                }

                return $this->check_in_time->diffInMinutes($this->check_out_time);
            },
        );
    }

    public function isActive(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                return $this->check_in_time && ! $this->check_out_time;
            },
        );
    }

    public function isBiometric(): bool
    {
        return $this->check_in_method === 'biometric';
    }

    public function isOfflineSync(): bool
    {
        return $this->synced_at !== null
            && $this->check_in_time !== null
            && $this->synced_at->gt($this->check_in_time);
    }

    public function wasDoorUnlocked(): bool
    {
        return $this->door_status === 'unlocked';
    }

    #[Scope]
    protected function biometric($query): void
    {
        $query->where('check_in_method', 'biometric');
    }

    // Scope for filtering by date
    #[Scope]
    protected function byDate($query, $date): void
    {
        $query->whereDate('check_in_time', $date);
    }

    // Scope for filtering by gym
    #[Scope]
    protected function byGym($query, $gymId): void
    {
        $query->where('gym_id', $gymId);
    }

    // Scope for active attendance (checked in but not checked out)
    #[Scope]
    protected function active($query): void
    {
        $query->whereNotNull('check_in_time')
            ->whereNull('check_out_time');
    }

    // Scope for completed attendance
    #[Scope]
    protected function completed($query): void
    {
        $query->whereNotNull('check_in_time')
            ->whereNotNull('check_out_time');
    }

    // Scope for absent attendance
    #[Scope]
    protected function absent($query): void
    {
        $query->whereNull('check_in_time')
            ->whereNull('check_out_time');
    }

    // Get today's attendance for a specific member
    public static function getTodayAttendance($memberId, $gymId, $attendanceableType)
    {
        return static::where('attendanceable_id', $memberId)
            ->where('attendanceable_type', $attendanceableType)
            ->where('gym_id', $gymId)
            ->whereDate('check_in_time', now()->toDateString())
            ->first();
    }

    // Check if member is currently checked in
    public static function isCurrentlyCheckedIn($memberId, $gymId, $attendanceableType)
    {
        return static::where('attendanceable_id', $memberId)
            ->where('attendanceable_type', $attendanceableType)
            ->where('gym_id', $gymId)
            ->active()
            ->exists();
    }

    // Validation rules
    public static function validationRules(): array
    {
        return [
            'attendanceable_id' => 'required|exists:gym_members,id',
            'gym_id' => 'required|exists:gyms,id',
            'check_in_time' => 'required|date',
            'check_out_time' => 'nullable|date|after:check_in_time',
            'status' => 'required|in:Present,Absent,Completed',
            'note' => 'nullable|string|max:500',
        ];
    }
}
