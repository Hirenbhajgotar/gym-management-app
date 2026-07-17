<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\InvoiceablePayment;
use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Observers\MemberPaymentObserver;
use Database\Factories\MemberPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
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
 * @property-read float $total_paid
 * @property-read float $remaining_amount
 * @property-read bool $is_fully_paid
 * @property-read mixed $formatted_amount
 * @property-read Gym|null $gym
 * @property-read GymMember|null $member
 * @property-read MembershipPlan|null $membershipPlan
 * @property-read Collection<int, MemberPaymentTransaction> $transactions
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
 */
#[ObservedBy([MemberPaymentObserver::class])]
class MemberPayment extends Model implements InvoiceablePayment
{
    use HasFactory, LogsActivity;

    public const DEFAULT_CURRENCY = Currency::INR;

    protected $fillable = [
        'gym_id',
        'member_id',
        'membership_plan_id',
        'amount',
        'discount_amount',
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'payment_date' => 'date',
            'membership_start' => 'date',
            'membership_end' => 'date',
            'status' => PaymentStatus::class,
            'currency' => Currency::class,
        ];
    }

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

        $this->loadMissing('member');

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

    public function getInvoiceOwner(): ?Model
    {
        return $this->member;
    }

    public function getGym(): ?Gym
    {
        return $this->gym;
    }

    public function getInvoiceKey(): int
    {
        return $this->id;
    }

    public function getOwnerId(): ?int
    {
        return $this->member_id;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoice_number;
    }

    public function getInvoicePath(): ?string
    {
        return $this->invoice_path;
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->created_at;
    }

    public function setInvoiceNumber(string $number): void
    {
        $this->invoice_number = $number;
    }

    public function setInvoicePath(string $path): void
    {
        $this->invoice_path = $path;
    }

    public function saveInvoiceMetaQuietly(): void
    {
        $this->saveQuietly();
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
     * @return HasMany<MemberPaymentTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(MemberPaymentTransaction::class);
    }

    /**
     * Eager-load a completed-transactions sum for list/table views to avoid
     * per-row N+1 queries. Use alongside `total_paid_sum` instead of the
     * `total_paid` accessor when rendering collections.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function withPaymentTotals(Builder $query): Builder
    {
        return $query->withSum(
            ['transactions as total_paid_sum' => fn (Builder $q) => $q->where('status', 'completed')],
            'amount'
        );
    }

    /** @return Attribute<float, never> */
    protected function totalPaid(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->relationLoaded('transactions')) {
                    $paidAmount = $this->transactions
                        ->where('status', 'completed')
                        ->sum('amount');

                    return is_numeric($paidAmount) ? (float) $paidAmount : 0.0;
                }

                // Aggregate at the DB level instead of hydrating rows just to sum them.
                return (float) $this->transactions()
                    ->where('status', 'completed')
                    ->sum('amount');
            }
        );
    }

    /** @return Attribute<float, never> */
    protected function remainingAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => max(0, (float) $this->amount - $this->total_paid),
        );
    }

    /** @return Attribute<bool, never> */
    protected function isFullyPaid(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->remaining_amount <= 0,
        );
    }

    public function syncStatusFromTransactions(): void
    {
        $status = match (true) {
            $this->is_fully_paid => PaymentStatus::COMPLETED,
            $this->total_paid > 0 => PaymentStatus::PARTIAL,
            default => PaymentStatus::PENDING,
        };

        if ($this->status !== $status) {
            $this->update(['status' => $status]);
        }
    }

    /**
     * Get formatted amount
     */
    protected function formattedAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => Number::currency(
                (float) $this->amount,
                in: $this->currency->value ?? self::DEFAULT_CURRENCY->value
            ),
        );
    }
}
