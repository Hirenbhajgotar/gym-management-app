<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GymMember extends Model
{
    use LogsActivity;

    protected $fillable = [
        'gym_id',
        'name',
        'profile_photo',
        'email',
        'phone',
        'address',
        'weight',
        'height',
        'age',
        'dob',
        'membership_start',
        'membership_end',
        'gym_membership_plan_id',
        'user_id',
    ];

    protected $casts = [
        'dob' => 'date',
        'membership_start' => 'date',
        'membership_end' => 'date',
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
        'age' => 'integer',
    ];

    protected static function booted()
    {
        static::saving(function ($member) {
            if ($member->membership_start && $member->gym_membership_plan_id) {
                $plan = $member->membershipPlan;
                if ($plan && $plan->duration) {
                    $member->membership_end = Carbon::parse($member->membership_start)->addMonths($plan->duration);
                }
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'gym.name',
                'name',
                'email',
                'phone',
                'address',
                'membership_start',
                'membership_end',
                'membershipPlan.name',
            ])
            ->useLogName('gym_member')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        if ($eventName === 'updated') {
            $changes = $this->getChanges();

            // Remove timestamps from changes
            unset($changes['updated_at']);

            if (empty($changes)) {
                return "{$this->name}'s membership was renewed at {$this->gym?->name}";
            }

            $changedFields = collect($changes)->keys()->map(function ($field) {
                return str_replace('_', ' ', ucfirst($field));
            })->implode(', ');

            return "{$this->name}'s information was updated at {$this->gym?->name} (Changed: {$changedFields})";
        }

        return match ($eventName) {
            'created' => "{$this->name} joined {$this->gym?->name}",
            'deleted' => "{$this->name} was removed from {$this->gym?->name}",
            default => parent::getDescriptionForEvent($eventName),
        };
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    public function membershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class, 'gym_membership_plan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function payments()
    {
        return $this->hasMany(MemberPayment::class, 'member_id', 'id');
    }
}
