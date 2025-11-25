<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $gym_id
 * @property string|null $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $specialization
 * @property int|null $experience_years
 * @property string|null $certifications
 * @property bool|null $is_available
 * @property string|null $available_hours
 * @property numeric|null $basic_salary
 * @property numeric|null $pt_salary_per_member
 * @property string|null $currency
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attendances> $attendances
 * @property-read int|null $attendances_count
 * @property-read float $total_earnings
 * @property-read \App\Models\Gym $gym
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GymMember> $members
 * @property-read int|null $members_count
 *
 * @method static Builder<static>|Trainer available()
 * @method static \Database\Factories\TrainerFactory factory($count = null, $state = [])
 * @method static Builder<static>|Trainer newModelQuery()
 * @method static Builder<static>|Trainer newQuery()
 * @method static Builder<static>|Trainer query()
 * @method static Builder<static>|Trainer whereAvailableHours($value)
 * @method static Builder<static>|Trainer whereBasicSalary($value)
 * @method static Builder<static>|Trainer whereCertifications($value)
 * @method static Builder<static>|Trainer whereCreatedAt($value)
 * @method static Builder<static>|Trainer whereCurrency($value)
 * @method static Builder<static>|Trainer whereEmail($value)
 * @method static Builder<static>|Trainer whereExperienceYears($value)
 * @method static Builder<static>|Trainer whereGymId($value)
 * @method static Builder<static>|Trainer whereId($value)
 * @method static Builder<static>|Trainer whereIsAvailable($value)
 * @method static Builder<static>|Trainer whereName($value)
 * @method static Builder<static>|Trainer wherePhone($value)
 * @method static Builder<static>|Trainer wherePtSalaryPerMember($value)
 * @method static Builder<static>|Trainer whereSpecialization($value)
 * @method static Builder<static>|Trainer whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperTrainer
 */
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
        'profile_photo',
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
