<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Notifications\MemberFrozenNotification;
use App\Notifications\MembershipExpiryNotification;
use App\Observers\GymMemberObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
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
 * @property float|null $weight Weight in kg
 * @property float|null $height Height in cm
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
 *
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
 *
 * @mixin IdeHelperGymMember
 * @mixin \Eloquent
 */
class GymMember extends Model
{
    use HasFactory, LogsActivity, Notifiable;

    public const NAVIGATION_BADGE_CACHE_KEY = 'gym_member_navigation_badge_count_';

    public const MEMBER_WEIGHT_PROGRESS_CACHE_KEY = 'member_weight_progress_';

    protected $fillable = [
        'gym_id',
        'name',
        'profile_photo',
        'email',
        'phone',
        'age',
        'dob',
        'membership_start',
        'membership_end',
        'payment_status',
        'latest_payment_at',
        'gym_membership_plan_id',
        'trainer_id',
        'user_id',
        'lead_id',
        'is_frozen',
        'frozen_at',
        'frozen_until',
        'frozen_reason',
        'frozen_by',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'membership_start' => 'date',
            'membership_end' => 'date',
            'age' => 'integer',
            'latest_payment_at' => 'date',
            'payment_status' => PaymentStatus::class,
            'is_frozen' => 'boolean',
            'frozen_at' => 'datetime',
            'frozen_until' => 'datetime',
        ];
    }

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

    // =========================
    // Relationships
    // =========================

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(MemberPayment::class, 'member_id', 'id');
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(MemberPayment::class, 'member_id')->latestOfMany();
    }

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    public function attendances(): MorphMany
    {
        return $this->morphMany(Attendances::class, 'attendanceable');
    }

    public function lastAttendance(): MorphOne
    {
        return $this->morphOne(Attendances::class, 'attendanceable')->latestOfMany('check_in_time');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(MemberMeasurement::class)->orderBy('measured_at', 'desc');
    }

    public function frozenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'frozen_by');
    }

    public function biometricTemplates(): HasMany
    {
        return $this->hasMany(BiometricTemplate::class, 'gym_member_id');
    }

    public function biometricConsents(): HasMany
    {
        return $this->hasMany(BiometricConsent::class, 'gym_member_id');
    }

    public function activeBiometricConsent(): ?BiometricConsent
    {
        return $this->biometricConsents()
            ->where('gym_id', $this->gym_id)
            ->whereNull('revoked_at')
            ->first();
    }

    public function hasActiveBiometricConsent(): bool
    {
        return $this->activeBiometricConsent() !== null;
    }

    // =========================
    // Activity Log
    // =========================

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
                return "{$this->name}'s membership was renewed";
            }

            $changedFields = collect($changes)->keys()->map(function ($field) {
                return str_replace('_', ' ', ucfirst($field));
            })->implode(', ');

            return "{$this->name}'s information was updated (Changed: {$changedFields})";
        }

        return match ($eventName) {
            'created' => "{$this->name} joined",
            'deleted' => "{$this->name} was removed",
            default => "Gym member {$eventName}",
        };
    }

    // =========================
    // Scope
    // =========================

    #[Scope]
    protected function membershipActive($query)
    {
        return $query->where('membership_end', '>=', today());
    }

    #[Scope]
    protected function membershipExpired($query)
    {
        return $query->where('membership_end', '<', today());
    }

    #[Scope]
    protected function hasTrainer($query)
    {
        return $query->whereNotNull('trainer_id');
    }

    /**
     * Scope to get non-frozen members
     */
    #[Scope]
    protected function notFrozen($query)
    {
        return $query->where(function ($q) {
            $q->where('is_frozen', false)
                ->orWhere(function ($q2) {
                    $q2->where('is_frozen', true)
                        ->whereNotNull('frozen_until')
                        ->where('frozen_until', '<', now());
                });
        });
    }

    /**
     * Scope to get frozen members
     */
    #[Scope]
    protected function frozen($query)
    {
        return $query->where('is_frozen', true)
            ->where(function ($q) {
                $q->whereNull('frozen_until')
                    ->orWhere('frozen_until', '>=', now());
            });
    }

    // =========================
    // Helper methods
    // =========================

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

    public function currentAttendance(): ?Attendances
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

    // =========================
    // Freeze Methods
    // =========================

    /**
     * Check if member is currently frozen
     */
    public function isFrozen(): bool
    {
        if (! $this->is_frozen) {
            return false;
        }

        // If frozen until is set and has passed, auto-unfreeze
        if ($this->frozen_until && $this->frozen_until->isPast()) {
            $this->unfreeze();

            return false;
        }

        return true;
    }

    /**
     * Check if member can access gym facilities
     * Combines membership status, payment status, and freeze status
     */
    public function canAccessGym(): bool
    {
        // Check if membership is active
        if (! $this->membership_end || $this->membership_end->isPast()) {
            return false;
        }

        // Check if payment is completed
        if ($this->payment_status !== PaymentStatus::COMPLETED) {
            return false;
        }

        // Check if frozen
        if ($this->isFrozen()) {
            return false;
        }

        return true;
    }

    /**
     * Freeze member (manual or date-based)
     */
    public function freeze(?string $reason = null, ?int $days = null, ?int $frozenById = null): bool
    {
        $this->is_frozen = true;
        $this->frozen_at = now();
        $this->frozen_reason = $reason;
        $this->frozen_by = $frozenById;

        if ($days !== null && $days > 0) {
            $this->frozen_until = now()->addDays($days);
        } else {
            $this->frozen_until = null;
        }

        $this->save();

        // Send email notification
        $this->notify(new MemberFrozenNotification(
            member: $this,
            isFrozen: true,
            reason: $reason,
            frozenUntil: $this->frozen_until
        ));

        return true;
    }

    /**
     * Unfreeze member
     */
    public function unfreeze(?int $unfrozenById = null): bool
    {
        $this->is_frozen = false;
        $this->frozen_at = null;
        $this->frozen_until = null;
        $this->frozen_reason = null;
        $this->frozen_by = $unfrozenById;
        $this->save();

        // Send email notification
        $this->notify(new MemberFrozenNotification(
            member: $this,
            isFrozen: false
        ));

        return true;
    }
}
