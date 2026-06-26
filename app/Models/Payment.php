<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Models\Activity;
use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Observers\PaymentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $gym_id
 * @property string $razorpay_order_id
 * @property string|null $razorpay_payment_id
 * @property string|null $razorpay_signature
 * @property numeric|null $amount
 * @property Currency|null $currency
 * @property PaymentStatus|null $status
 * @property string|null $payment_type
 * @property array<array-key, mixed>|null $metadata
 * @property Carbon|null $paid_at
 * @property Carbon|null $refunded_at
 * @property numeric|null $refund_amount
 * @property string|null $invoice_number
 * @property string|null $invoice_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read mixed $formatted_amount
 * @property-read Gym|null $gym
 * @property-read User|null $user
 *
 * @method static Builder<static>|Payment byType(string $type)
 * @method static Builder<static>|Payment completed()
 * @method static Builder<static>|Payment failed()
 * @method static Builder<static>|Payment newModelQuery()
 * @method static Builder<static>|Payment newQuery()
 * @method static Builder<static>|Payment pending()
 * @method static Builder<static>|Payment query()
 * @method static Builder<static>|Payment whereAmount($value)
 * @method static Builder<static>|Payment whereCreatedAt($value)
 * @method static Builder<static>|Payment whereCurrency($value)
 * @method static Builder<static>|Payment whereGymId($value)
 * @method static Builder<static>|Payment whereId($value)
 * @method static Builder<static>|Payment whereInvoiceNumber($value)
 * @method static Builder<static>|Payment whereInvoicePath($value)
 * @method static Builder<static>|Payment whereMetadata($value)
 * @method static Builder<static>|Payment wherePaidAt($value)
 * @method static Builder<static>|Payment wherePaymentType($value)
 * @method static Builder<static>|Payment whereRazorpayOrderId($value)
 * @method static Builder<static>|Payment whereRazorpayPaymentId($value)
 * @method static Builder<static>|Payment whereRazorpaySignature($value)
 * @method static Builder<static>|Payment whereRefundAmount($value)
 * @method static Builder<static>|Payment whereRefundedAt($value)
 * @method static Builder<static>|Payment whereStatus($value)
 * @method static Builder<static>|Payment whereUpdatedAt($value)
 * @method static Builder<static>|Payment whereUserId($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperPayment
 */
#[ObservedBy([PaymentObserver::class])]
class Payment extends Model
{
    use HasFactory, LogsActivity;

    public const PROFILE_LATEST_PAYMENTS_OF_AUTH_USER_CACHE_KEY = 'profile_latest_payments_of_auth_user_';

    protected $fillable = [
        'user_id',
        'gym_id',
        'razorpay_order_id',
        'razorpay_payment_id',
        'razorpay_signature',
        'amount',
        'currency',
        'status',
        'payment_type',
        'metadata',
        'paid_at',
        'refunded_at',
        'refund_amount',
        'invoice_number',
        'invoice_path',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'refund_amount' => 'decimal:2',
            'metadata' => 'array',
            'status' => PaymentStatus::class,
            'currency' => Currency::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('payment')
            ->logOnly(['gym.name', 'amount', 'currency', 'status', 'payment_type', 'razorpay_order_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        $amount = Number::currency($this->amount, in: $this->currency->value);

        return match ($eventName) {
            'created' => "Payment of {$amount} created for {$this->gym?->name} ({$this->payment_type})",
            'updated' => "Payment of {$amount} updated for {$this->gym?->name} ({$this->payment_type})",
            'deleted' => "Payment of {$amount} deleted for {$this->gym?->name} ({$this->payment_type})",
            default => "Payment {$eventName} for {$this->gym?->name} ({$this->payment_type})",
        };
    }

    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if payment is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === PaymentStatus::COMPLETED;
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    /**
     * Check if payment failed
     */
    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::FAILED;
    }

    /**
     * Check if payment is expired
     */
    public function isExpired(): bool
    {
        return $this->status === PaymentStatus::EXPIRED;
    }

    /**
     * Mark payment as completed
     */
    public function markAsCompleted(): bool
    {
        return $this->update([
            'status' => PaymentStatus::COMPLETED,
            'paid_at' => now(),
        ]);
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed(): bool
    {
        return $this->update(['status' => PaymentStatus::FAILED]);
    }

    /**
     * Process refund
     */
    public function processRefund(?float $amount = null): bool
    {
        $refundAmount = $amount ?? $this->amount;

        return $this->update([
            'refunded_at' => now(),
            'refund_amount' => $refundAmount,
        ]);
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

    /**
     * Scope for completed payments
     */
    #[Scope]
    protected function completed(Builder $query)
    {
        return $query->where('status', PaymentStatus::COMPLETED);
    }

    /**
     * Scope for pending payments
     */
    #[Scope]
    protected function pending(Builder $query)
    {
        return $query->where('status', PaymentStatus::PENDING);
    }

    /**
     * Scope for failed payments
     */
    #[Scope]
    protected function failed(Builder $query)
    {
        return $query->where('status', PaymentStatus::FAILED);
    }

    /**
     * Scope for payments by type
     */
    #[Scope]
    protected function byType(Builder $query, string $type)
    {
        return $query->where('payment_type', $type);
    }

    /**
     * Check if payment has valid signature stored
     */
    public function hasValidSignature(): bool
    {
        return filled($this->razorpay_signature);
    }
}
