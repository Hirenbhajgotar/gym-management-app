<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Notifications\MembershipExpiryNotification;
use App\Observers\GymMemberObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[ObservedBy([GymMemberObserver::class])]
class GymMember extends Model
{
    use HasFactory, LogsActivity, Notifiable;

    protected $fillable = [
        'gym_id',
        'name',
        'profile_photo',
        'email',
        'phone',
        'weight',
        'height',
        'age',
        'dob',
        'membership_start',
        'membership_end',
        'payment_status',
        'gym_membership_plan_id',
        'trainer_id',
        'user_id',
        'lead_id',
    ];

    protected $casts = [
        'dob' => 'date',
        'membership_start' => 'date',
        'membership_end' => 'date',
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
        'age' => 'integer',
        'payment_status' => PaymentStatus::class,
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
                'trainer.name',
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

    public function avatar(): string
    {
        if ($this->profile_photo) {
            return $this->profile_photo;
        }

        $name = urlencode($this->name);
        $size = 40;
        $background = 'random';
        $color = 'FFFFFF';

        return "https://ui-avatars.com/api/?name={$name}&size={$size}&background={$background}&color={$color}&bold=true";
    }

    public function notifyExpiry(int $daysLeft): void
    {
        $this->notify(new MembershipExpiryNotification(
            member: $this,
            expiresAt: $this->membership_end,
            daysLeft: $daysLeft
        ));
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'gym_membership_plan_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function payments()
    {
        return $this->hasMany(MemberPayment::class, 'member_id', 'id');
    }

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
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

    public function scopeMembershipActive($query)
    {
        return $query->where('membership_end', '>=', today());
    }

    public function scopeMembershipExpired($query)
    {
        return $query->where('membership_end', '<', today());
    }

    public function scopeHasTrainer($query)
    {
        return $query->whereNotNull('trainer_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
