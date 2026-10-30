<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Auth\Access\AuthorizationException;
use App\Biometrics\Exceptions\MemberArchivedException;
use App\Enums\PaymentStatus;
use App\Filament\Admin\Resources\GymMemberResource;
use App\Notifications\MembershipExpiryNotification;
use App\Observers\GymMemberObserver;
use App\Services\MemberArchiveService;
use App\Services\MemberFreezeService;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[ObservedBy([GymMemberObserver::class])]
/**
 * @property int $id
 * @property int $gym_id
 * @property string $name
 * @property string|null $profile_photo
 * @property string|null $email
 * @property string $phone
 * @property int|null $age
 * @property \Illuminate\Support\Carbon|null $dob
 * @property \Illuminate\Support\Carbon|null $membership_start
 * @property \Illuminate\Support\Carbon|null $membership_end
 * @property PaymentStatus|null $payment_status
 * @property \Illuminate\Support\Carbon|null $latest_payment_at
 * @property int|null $gym_membership_plan_id
 * @property int|null $trainer_id
 * @property int|null $membership_plan_id
 * @property int|null $user_id
 * @property bool $is_frozen
 * @property \Illuminate\Support\Carbon|null $frozen_at
 * @property \Illuminate\Support\Carbon|null $frozen_until
 * @property string|null $frozen_reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $frozen_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\Address|null $address
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attendances> $attendances
 * @property-read int|null $attendances_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BiometricConsent> $biometricConsents
 * @property-read int|null $biometric_consents_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BiometricTemplate> $biometricTemplates
 * @property-read int|null $biometric_templates_count
 * @property-read \App\Models\User|null $frozenBy
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\Attendances|null $lastAttendance
 * @property-read \App\Models\MemberPayment|null $latestPayment
 * @property-read float $total_payments_amount
 * @property-read float $outstanding_balance
 * @property-read \App\Models\Lead|null $lead
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MemberMeasurement> $measurements
 * @property-read int|null $measurements_count
 * @property-read \App\Models\MembershipPlan|null $membershipPlan
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MemberPayment> $payments
 * @property-read int|null $payments_count
 * @property-read \App\Models\Trainer|null $trainer
 * @property-read \App\Models\User|null $user
 *
 * @method static \Database\Factories\GymMemberFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember frozen()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember hasTrainer()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember membershipActive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember membershipExpired()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember notFrozen()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember withoutActiveBiometricConsent(int $gymId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember membershipEndingOn(\Illuminate\Support\Carbon $date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereAge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereDob($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereFrozenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereFrozenBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereFrozenReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereFrozenUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereGymMembershipPlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereIsFrozen($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GymMember whereLatestPaymentAt($value)
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
 *
 * @mixin \Eloquent
 */
class GymMember extends Model
{
    use HasFactory, LogsActivity, Notifiable;

    public const NAVIGATION_BADGE_CACHE_KEY = 'gym_member_navigation_badge_count_';

    public const MEMBER_WEIGHT_PROGRESS_CACHE_KEY = 'member_weight_progress_';

    public const DEFAULT_MIN_FREEZE_DAYS = 7;

    public const DEFAULT_MAX_FREEZE_DAYS = 60;

    /**
     * Request-local cache for payments+transactions so total_payments_amount
     * and outstanding_balance don't each fire their own query.
     *
     * @var Collection<int, MemberPayment>|null
     */
    private ?Collection $paymentsWithTransactionsCache = null;

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
        'extend_membership',
    ];

    /**
     * The archive and consent columns are deliberately NOT mass-assignable.
     *
     * The enforcement is their ABSENCE FROM $fillable above, not this array:
     * with a non-empty $fillable, Eloquent ignores $guarded entirely, so the
     * list below changes no behaviour and removing it would be safe. It is
     * retained only as an explicit, greppable marker of the four columns the
     * archive invariant owns, owned and written by
     * {@see \App\Services\MemberArchiveService} through forceFill() — so a
     * future `update($request->validated())` / `fill($request->all())` cannot
     * flip archive state or the consent round-trip without the gate.
     */
    protected $guarded = [
        'is_archived',
        'archived_at',
        'allow_whatsapp_marketing',
        'whatsapp_marketing_snapshot',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array{
     *     dob: 'date',
     *     membership_start: 'date',
     *     membership_end: 'date',
     *     gym_id: 'integer',
     *     age: 'integer',
     *     latest_payment_at: 'date',
     *     payment_status: 'App\Enums\PaymentStatus',
     *     is_frozen: 'boolean',
     *     frozen_at: 'datetime',
     *     frozen_until: 'datetime',
     *     extend_membership: 'boolean',
     *     is_archived: 'boolean',
     *     archived_at: 'datetime',
     *     allow_whatsapp_marketing: 'boolean',
     *     whatsapp_marketing_snapshot: 'boolean',
     * }
     */
    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'membership_start' => 'date',
            'membership_end' => 'date',
            'gym_id' => 'integer',
            'age' => 'integer',
            'latest_payment_at' => 'date',
            'payment_status' => PaymentStatus::class,
            'is_frozen' => 'boolean',
            'frozen_at' => 'datetime',
            'frozen_until' => 'datetime',
            'extend_membership' => 'boolean',
            'is_archived' => 'boolean',
            'archived_at' => 'datetime',
            'allow_whatsapp_marketing' => 'boolean',
            'whatsapp_marketing_snapshot' => 'boolean',
        ];
    }

    protected static function booted()
    {
        static::saving(function (self $member) {
            if (! $member->isDirty('membership_start')) {
                return;
            }

            $member->loadMissing('membershipPlan');

            if ($member->membership_start && $member->gym_membership_plan_id) {
                $plan = $member->membershipPlan;

                if ($plan && $plan->duration) {
                    $membershipEndDate = Date::parse($member->membership_start)
                        ->addMonths($plan->duration);

                    if (! $member->isDirty('membership_end')) {
                        $member->membership_end = $membershipEndDate;
                    }
                }
            }
        });
    }

    // =========================
    // Relationships
    // =========================
    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    /**
     * @return BelongsTo<MembershipPlan, $this>
     */
    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'gym_membership_plan_id');
    }

    /**
     * @return BelongsTo<Trainer, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<MemberPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(MemberPayment::class, 'member_id', 'id');
    }

    /**
     * @return HasOne<MemberPayment, $this>
     */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(MemberPayment::class, 'member_id')
            ->latestOfMany();
    }

    /**
     * @return MorphOne<Address, $this>
     */
    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    /**
     * @return MorphMany<Attendances, $this>
     */
    public function attendances(): MorphMany
    {
        return $this->morphMany(Attendances::class, 'attendanceable');
    }

    /**
     * @return MorphOne<Attendances, $this>
     */
    public function lastAttendance(): MorphOne
    {
        return $this->morphOne(Attendances::class, 'attendanceable')->latestOfMany('check_in_time');
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return HasMany<MemberMeasurement, $this>
     */
    public function measurements(): HasMany
    {
        return $this->hasMany(MemberMeasurement::class)->latest('measured_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function frozenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'frozen_by');
    }

    /**
     * @return HasMany<BiometricTemplate, $this>
     */
    public function biometricTemplates(): HasMany
    {
        return $this->hasMany(BiometricTemplate::class, 'gym_member_id');
    }

    /**
     * @return HasMany<BiometricConsent, $this>
     */
    public function biometricConsents(): HasMany
    {
        return $this->hasMany(BiometricConsent::class, 'gym_member_id');
    }

    /**
     * @return HasOne<BiometricConsent, $this>
     */
    public function biometricConsent(): HasOne
    {
        return $this->hasOne(BiometricConsent::class, 'gym_member_id')->whereNull('revoked_at')->latestOfMany();
    }

    public function activeBiometricConsent(): ?BiometricConsent
    {
        if ($this->relationLoaded('biometricConsents')) {
            return $this->biometricConsents
                ->where('gym_id', $this->gym_id)
                ->whereNull('revoked_at')
                ->first();
        }

        return $this->biometricConsents()
            ->where('gym_id', $this->gym_id)
            ->whereNull('revoked_at')
            ->first();
    }

    public function hasActiveBiometricConsent(bool $fresh = false): bool
    {
        if (! $fresh && array_key_exists('biometric_consents_exists', $this->attributes)) {
            return (bool) $this->attributes['biometric_consents_exists'];
        }

        return $this->biometricConsents()
            ->where('gym_id', $this->gym_id)
            ->whereNull('revoked_at')
            ->exists();
    }

    /**
     * Whether this member has ever withdrawn a biometric consent.
     *
     * The mirror image of hasActiveBiometricConsent(), and deliberately not its
     * negation: a member can hold a revoked row and no active one. Revocation is
     * the member exercising a compliance right, so it has to be able to end the
     * consent state — it must not be something the next inbound link undoes.
     */
    public function hasRevokedBiometricConsent(bool $fresh = false): bool
    {
        return $this->biometricConsents()
            ->where('gym_id', $this->gym_id)
            ->whereNotNull('revoked_at')
            ->exists();
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

            if (blank($changes)) {
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

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function membershipActive(Builder $query): void
    {
        $query->where('membership_end', '>=', today());
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function membershipExpired(Builder $query): void
    {
        $query->where('membership_end', '<', today());
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function hasTrainer(Builder $query): void
    {
        $query->whereNotNull('trainer_id');
    }

    /**
     * Scope to get non-frozen members
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function notFrozen(Builder $query): void
    {
        $query->where(function (\Illuminate\Contracts\Database\Query\Builder $q) {
            $q->where('is_frozen', false)
                ->orWhere(function (\Illuminate\Contracts\Database\Query\Builder $q2) {
                    $q2->where('is_frozen', true)
                        ->whereNotNull('frozen_until')
                        ->where('frozen_until', '<', now());
                });
        });
    }

    /**
     * Scope to get frozen members
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function frozen(Builder $query): void
    {
        $query->where('is_frozen', true)
            ->where(function (\Illuminate\Contracts\Database\Query\Builder $q) {
                $q->whereNull('frozen_until')
                    ->orWhere('frozen_until', '>=', now());
            });
    }

    /**
     * Scope to get members whose membership ends on a specific date.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function membershipEndingOn(Builder $query, Carbon $date): void
    {
        $query->whereDate('membership_end', $date);
    }

    /**
     * Scope to get non-archived members.
     *
     * Deliberately opt-in rather than a global scope. Archived members are
     * history, not rubbish: their payments, invoices, attendance and consent
     * records all still have to resolve to a person, and a global scope hid
     * them from every relationship eager-load as well as every query — silently
     * blanking revenue-export identity, inverting invoice ownership checks and
     * under-counting lifetime value. A caller that means "the current roster"
     * says so here; a caller that means "this member's history" is left alone.
     *
     * The security consequence of archiving is enforced at the decision points
     * (see canAccessGym()), not by hiding rows, so it cannot be bypassed by
     * whichever query happened to load the model.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function notArchived(Builder $query): void
    {
        $query->where('is_archived', false);
    }

    /**
     * Scope to get archived members.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function archived(Builder $query): void
    {
        $query->where('is_archived', true);
    }

    /**
     * Scope to members without an active biometric consent.
     *
     * $gymId is required rather than nullable: omitting it silently drops the
     * tenant predicate and scans every gym's consents, which is more restrictive,
     * not less — a footgun rather than a leak, but still never what the caller meant.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function withoutActiveBiometricConsent(Builder $query, int $gymId): void
    {
        $query->whereDoesntHave('biometricConsents', function (Builder $q) use ($gymId) {
            $q->where('gym_id', $gymId);
            $q->whereNull('revoked_at');
        });
    }

    /**
     * Scope to get members eligible for WhatsApp marketing broadcasts.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function whatsappMarketingAllowed(Builder $query): void
    {
        $query->where('is_archived', false)
            ->where('allow_whatsapp_marketing', true);
    }

    /**
     * Scope to get members whose birthday is today.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function birthdayToday(Builder $query): void
    {
        $query->whereMonth('dob', now()->month)
            ->whereDay('dob', now()->day);
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
        if (! $this->membership_end) {
            return;
        }

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
     * Member is considered actively frozen if:
     * 1. is_frozen is true AND
     * 2. (frozen_until is null [manual freeze] OR frozen_until is in the future)
     */
    public function isFrozen(): bool
    {
        if (! $this->is_frozen) {
            return false;
        }

        // If frozen_until is set and has passed, member is no longer actively frozen
        $frozenUntil = $this->frozen_until;
        /** @var Carbon|null $frozenUntil */
        if ($frozenUntil !== null && $frozenUntil->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Persist the unfreeze if a time-based freeze has expired.
     * Call this explicitly from a scheduled command / job — NOT from
     * read paths — since it writes to the DB and sends a notification.
     */
    public function autoUnfreezeIfExpired(bool $notify = true): bool
    {
        return resolve(MemberFreezeService::class)->autoUnfreezeIfExpired($this, $notify);
    }

    /**
     * Check if member can access gym facilities
     * Combines archive status, membership status, payment status, and freeze status
     *
     * Archive is checked first and on the model rather than being left to the
     * caller's query. This is the predicate that opens a door, so revoking access
     * has to be a property of the member and not of whichever query happened to
     * hydrate them — a check-in resolved through a relation, a raw report query or
     * an operator restoring a record must all land on the same answer. Archived
     * first also matters for the reason reported: an archived member with a live
     * membership is revoked *because they were archived*, and saying "membership
     * expired" would send the operator looking in the wrong place.
     */
    public function canAccessGym(): bool
    {
        if ($this->is_archived) {
            return false;
        }

        $membershipEnd = $this->membership_end;
        /** @var Carbon|null $membershipEnd */
        if ($membershipEnd === null || $membershipEnd->isPast()) {
            return false;
        }

        if ($this->payment_status === null || ! in_array($this->payment_status, [PaymentStatus::COMPLETED, PaymentStatus::PARTIAL], true)) {
            return false;
        }

        if ($this->isFrozen()) {
            return false;
        }

        return true;
    }

    /**
     * Accessor to get the member's active membership plan.
     * If no active plan, return null.
     */
    public function getActiveMembershipPlan(): ?MembershipPlan
    {
        $membershipEnd = $this->membership_end;
        /** @var Carbon|null $membershipEnd */
        if ($membershipEnd && $membershipEnd->isFuture()) {
            return $this->membershipPlan;
        }

        return null;
    }

    /**
     * Check if the member has outstanding payments.
     */
    public function hasOutstandingPayments(): bool
    {
        $latestPayment = $this->latestPayment;
        /** @var MemberPayment|null $latestPayment */
        if ($latestPayment && $latestPayment->status === PaymentStatus::PENDING) {
            return true;
        }

        return false;
    }

    /**
     * Load payments with their transactions once per request, whether
     * accessed via total_payments_amount, outstanding_balance, or both.
     *
     * @return Collection<int, MemberPayment>
     */
    protected function loadPaymentsWithTransactions(): Collection
    {
        if ($this->relationLoaded('payments')) {
            return $this->payments;
        }

        return $this->paymentsWithTransactionsCache ??= $this->payments()->with('transactions')->get();
    }

    /** @return Attribute<float, never> */
    protected function totalPaymentsAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->loadPaymentsWithTransactions()
                ->sum(fn (MemberPayment $payment) => $payment->total_paid),
        );
    }

    /**
     * Outstanding balance attribute: (Sum of plan amounts) - (Sum of paid amounts).
     *
     * @return Attribute<float, never>
     */
    protected function outstandingBalance(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                $payments = $this->loadPaymentsWithTransactions();

                $totalPlans = $payments->sum(fn (MemberPayment $payment): float => (float) $payment->amount);
                $totalPaid = $payments->sum(fn (MemberPayment $payment): float => $payment->total_paid);

                return max(0, $totalPlans - $totalPaid);
            },
        );
    }

    /**
     * Freeze member (manual or date-based)
     */
    public function freeze(?string $reason = null, ?int $days = null, ?int $frozenById = null, bool $extendMembership = false): bool
    {
        return resolve(MemberFreezeService::class)->freeze($this, $reason, $days, $frozenById, $extendMembership);
    }

    /**
     * Unfreeze member
     */
    public function unfreeze(?int $unfrozenById = null, bool $notify = true): bool
    {
        return resolve(MemberFreezeService::class)->unfreeze($this, $unfrozenById, $notify);
    }

    /**
     * Get maximum allowed freeze extension days considering both gym settings and plan duration
     */
    public function getMaxFreezeExtensionDays(int $maxSetting): int
    {
        return resolve(MemberFreezeService::class)->getMaxFreezeExtensionDays($this, $maxSetting);
    }

    /**
     * Sync latest payment membership_end date to match member's membership_end.
     * Note: Syncing latest non-expired payment is intentional as it represents the current active billing term.
     */
    public function syncLatestPaymentMembershipEnd(): void
    {
        resolve(MemberFreezeService::class)->syncLatestPaymentMembershipEnd($this);
    }

    // =========================
    // Archiving & Marketing
    // =========================

    /**
     * Check if the member can receive WhatsApp notifications or marketing messages.
     */
    public function canReceiveWhatsappNotifications(): bool
    {
        return ! $this->is_archived && $this->allow_whatsapp_marketing;
    }

    /**
     * Whether the member is permitted to hold a live biometric credential.
     *
     * The grant-side statement of the archive invariant: an archived member is
     * access-revoked, so no producer of a biometric credential may act on them.
     * The predicate is declared once here so every producer asks the same
     * question, the way {@see \App\Biometrics\Services\BiometricDeProvisioner}
     * is the single erase-side authority.
     */
    public function mayHoldBiometricCredential(): bool
    {
        return ! $this->is_archived;
    }

    /**
     * Refuse a grant-side biometric write when the member is archived.
     *
     * Prefer this over reading the `is_archived` column at each producer: a
     * producer that forgets the check quietly hands an archived member a live
     * credential, which is exactly the state the archive policy forbids.
     *
     * @throws MemberArchivedException
     */
    public function assertMayHoldBiometricCredential(): void
    {
        throw_unless($this->mayHoldBiometricCredential(), MemberArchivedException::class);
    }

    /**
     * Archive member record and disable WhatsApp marketing.
     *
     * The transition, the row lock and the permission check all live in
     * {@see MemberArchiveService} — the single owner of a member's archive state.
     * This helper deliberately does NOT read the ambient guard: on a shared
     * worker the guard holds whatever identity the previous job left logged in,
     * so reading it here would authorise an unattributable write as the previous
     * operator. The operator is passed in explicitly by the caller, and a caller
     * that cannot name one is refused rather than silently attributed.
     *
     * @throws AuthorizationException
     */
    public function archive(?User $operator = null): bool
    {
        return resolve(MemberArchiveService::class)->archive($this, $operator);
    }

    /**
     * Restore member record from archive.
     *
     * Guarded for the same reason as archive(): unarchiving is the more
     * dangerous direction — it reinstates consent from the snapshot and returns
     * the member to the roster — so it must not be reachable without an explicit
     * operator either. Delegates to {@see MemberArchiveService}, which owns the
     * gate and the row lock.
     *
     * @throws AuthorizationException
     */
    public function unarchive(?User $operator = null): bool
    {
        return resolve(MemberArchiveService::class)->unarchive($this, $operator);
    }

    /**
     * Toggle WhatsApp marketing preference.
     *
     * On a live member this is an ordinary consent write. An archived member's
     * consent lives in the archive snapshot — the column is forced false for as
     * long as they are archived — so the toggle is routed through
     * MemberArchiveService rather than writing the column directly, which would
     * corrupt the snapshot round-trip. That makes the "owned by the service
     * while archived" invariant hold by construction instead of relying on the
     * caller to pre-filter archived members.
     */
    public function toggleWhatsappMarketing(bool $allowed): bool
    {
        if ($this->is_archived) {
            return resolve(MemberArchiveService::class)->recordConsentWhileArchived($this, $allowed);
        }

        return $this->forceFill([
            'allow_whatsapp_marketing' => $allowed,
        ])->save();
    }

    public function memberResourceUrl(self|int $member, ?string $type = 'index'): string
    {
        $memberModel = $member instanceof self ? $member : self::query()->find($member);

        if (! $memberModel) {
            return GymMemberResource::getUrl('index');
        }

        return match ($type) {
            'view' => GymMemberResource::getUrl('view', ['record' => $memberModel->id]),
            'edit' => GymMemberResource::getUrl('edit', ['record' => $memberModel->id]),
            default => GymMemberResource::getUrl('index'),
        };
    }
}
