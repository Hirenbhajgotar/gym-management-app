<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Models\Activity;
use Database\Factories\MemberPaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Observers\MemberPaymentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string|null $invoice_number
 * @property string|null $invoice_path
 * @property int|null $gym_id
 * @property int|null $member_id
 * @property int|null $membership_plan_id
 * @property numeric|null $amount
 * @property Currency|null $currency
 * @property string|null $payment_method
 * @property PaymentStatus|null $status
 * @property string|null $notes
 * @property Carbon|null $payment_date
 * @property Carbon|null $membership_start
 * @property Carbon|null $membership_end
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read mixed $formatted_amount
 * @property-read Gym|null $gym
 * @property-read GymMember|null $member
 * @property-read MembershipPlan|null $membershipPlan
 *
 * @method static MemberPaymentFactory factory($count = null, $state = [])
 * @method static Builder<static>|MemberPayment newModelQuery()
 * @method static Builder<static>|MemberPayment newQuery()
 * @method static Builder<static>|MemberPayment query()
 * @method static Builder<static>|MemberPayment whereAmount($value)
 * @method static Builder<static>|MemberPayment whereCreatedAt($value)
 * @method static Builder<static>|MemberPayment whereCurrency($value)
 * @method static Builder<static>|MemberPayment whereGymId($value)
 * @method static Builder<static>|MemberPayment whereId($value)
 * @method static Builder<static>|MemberPayment whereInvoiceNumber($value)
 * @method static Builder<static>|MemberPayment whereInvoicePath($value)
 * @method static Builder<static>|MemberPayment whereMemberId($value)
 * @method static Builder<static>|MemberPayment whereMembershipEnd($value)
 * @method static Builder<static>|MemberPayment whereMembershipPlanId($value)
 * @method static Builder<static>|MemberPayment whereMembershipStart($value)
 * @method static Builder<static>|MemberPayment whereNotes($value)
 * @method static Builder<static>|MemberPayment wherePaymentDate($value)
 * @method static Builder<static>|MemberPayment wherePaymentMethod($value)
 * @method static Builder<static>|MemberPayment whereStatus($value)
 * @method static Builder<static>|MemberPayment whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperMemberPayment
 */
#[ObservedBy([MemberPaymentObserver::class])]
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
        'invoice_number',
        'invoice_path',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'membership_start' => 'date',
        'membership_end' => 'date',
        'status' => PaymentStatus::class,
        'currency' => Currency::class,
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
        $payment = $this->formatted_amount;

        return match ($eventName) {
            'created' => "Payment of {$payment} received from {$this->member?->name}",
            'updated' => "Payment of {$payment} updated for {$this->member?->name}",
            'deleted' => "Payment of {$payment} deleted for {$this->member?->name}",
            default => "Payment {$eventName} for {$this->member?->name}",
        };
    }

    /**
     * @return BelongsTo<GymMember, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(GymMember::class);
    }

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
        return $this->belongsTo(MembershipPlan::class);
    }

    /**
     * Get formatted amount
     */
    protected function formattedAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => Number::currency($this->amount, in: $this->currency->value),
        );
    }
}
