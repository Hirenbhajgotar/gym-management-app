<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int|null $gym_id
 * @property int|null $member_id
 * @property int|null $membership_plan_id
 * @property numeric|null $amount
 * @property string|null $currency
 * @property string|null $payment_method
 * @property PaymentStatus|null $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $payment_date
 * @property \Illuminate\Support\Carbon|null $membership_start
 * @property \Illuminate\Support\Carbon|null $membership_end
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\Gym|null $gym
 * @property-read \App\Models\GymMember|null $member
 * @property-read \App\Models\MembershipPlan|null $membershipPlan
 * @method static \Database\Factories\MemberPaymentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereMemberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereMembershipEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereMembershipPlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereMembershipStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment wherePaymentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberPayment whereUpdatedAt($value)
 * @mixin \Eloquent
 * @mixin IdeHelperMemberPayment
 */
class MemberPayment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'gym_id',
        'member_id',
        'membership_plan_id',
        'amount',
        'currency',
        'payment_method',
        'status',
        'notes',
        'payment_date',
        'membership_start',
        'membership_end',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'membership_start' => 'date',
        'membership_end' => 'date',
        'status' => PaymentStatus::class,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('member_payment')
            ->logOnly(['member.name', 'membershipPlan.name', 'amount', 'currency', 'payment_method', 'status', 'notes', 'payment_date', 'membership_start', 'membership_end'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        $payment = Number::currency($this->amount, in: $this->currency);

        return match ($eventName) {
            'created' => "Payment of {$payment} received from {$this->member?->name} at {$this->member?->gym?->name}",
            'updated' => "Payment of {$payment} updated for {$this->member?->name} at {$this->member?->gym?->name}",
            'deleted' => "Payment of {$payment} deleted for {$this->member?->name} at {$this->member?->gym?->name}",
            default => "Payment {$eventName} for {$this->member?->name} at {$this->member?->gym?->name}",
        };
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(GymMember::class);
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }
}
