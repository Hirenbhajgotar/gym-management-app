<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'status' => AttendanceStatus::class,
    ];

    public function attendanceable()
    {
        return $this->morphTo();
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    protected function duration(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (!$this->check_out_time) {
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
                if (!$this->check_in_time || !$this->check_out_time) {
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
                return $this->check_in_time && !$this->check_out_time;
            },
        );
    }

    // Scope for filtering by date
    public function scopeByDate($query, $date)
    {
        return $query->whereDate('check_in_time', $date);
    }

    // Scope for filtering by gym
    public function scopeByGym($query, $gymId)
    {
        return $query->where('gym_id', $gymId);
    }

    // Scope for active attendance (checked in but not checked out)
    public function scopeActive($query)
    {
        return $query->whereNotNull('check_in_time')
            ->whereNull('check_out_time');
    }

    // Scope for completed attendance
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('check_in_time')
            ->whereNotNull('check_out_time');
    }

    // Scope for absent attendance
    public function scopeAbsent($query)
    {
        return $query->whereNull('check_in_time')
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
