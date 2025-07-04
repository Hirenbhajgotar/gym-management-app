<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Trainer extends Model
{
    /** @use HasFactory<\Database\Factories\TrainerFactory> */
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'name',
        'email',
        'phone',
        'specialization',
        'experience_years',
        'certifications',
        'is_available',
        'available_hours',
        'basic_salary',
        'pt_salary_per_member',
        'currency',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'basic_salary' => 'decimal:2',
        'pt_salary_per_member' => 'decimal:2',
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(GymMember::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', 1);
    }

    public function getTotalEarningsAttribute(): float
    {
        $assignedMembersCount = $this->members()->count();
        return $this->basic_salary + ($this->pt_salary_per_member * $assignedMembersCount);
    }

    public function attendances(): MorphMany
    {
        return $this->morphMany(Attendances::class, 'attendanceable');
    }

    public function currentAttendance()
    {
        return $this->attendances()
            ->where('status', 'checked_in')
            ->whereDate('check_in_time', today())
            ->first();
    }

    public function isCurrentlyCheckedIn(): bool
    {
        return $this->currentAttendance() !== null;
    }
}
