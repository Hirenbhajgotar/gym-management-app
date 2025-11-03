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
/**
 * @property int $id
 * @property int $gym_id
 * @property string $name
 * @property string|null $profile_photo
 * @property string $email
 * @property string|null $phone
 * @property numeric|null $weight Weight in kg
 * @property numeric|null $height Height in cm
 * @property int|null $age
 * @property \Illuminate\Support\Carbon|null $dob
 * @property \Illuminate\Support\Carbon|null $membership_start
 * @property \Illuminate\Support\Carbon|null $membership_end
 * @property PaymentStatus|null $payment_status
 * @property int|null $gym_membership_plan_id
 * @property int|null $trainer_id
 * @property int|null $membership_plan_id
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\Address|null $address
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attendances> $attendances
 * @property-read int|null $attendances_count
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\Lead|null $lead
 * @property-read \App\Models\MembershipPlan|null $membershipPlan
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MemberPayment> $payments
 * @property-read int|null $payments_count
 * @property-read \App\Models\Trainer|null $trainer
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\GymMemberFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember hasTrainer()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember membershipActive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember membershipExpired()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereAge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereDob($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereGymMembershipPlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereHeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereMembershipEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereMembershipPlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereMembershipStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember wherePaymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereProfilePhoto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereTrainerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereWeight($value)
 * @mixin \Eloquent
 * @mixin IdeHelperGymMember
 */
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
