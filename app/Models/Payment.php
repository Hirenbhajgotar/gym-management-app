<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Payment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'gym_id',
        'razorpay_order_id',
        'razorpay_payment_id',
        'amount',
        'currency',
        'status',
        'payment_type',
        'metadata',
        'paid_at',
        'refunded_at',
        'refund_amount',
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
        $amount = Number::currency($this->amount, in: $this->currency);

        return match ($eventName) {
            'created' => "Payment of {$amount} created for {$this->gym?->name} ({$this->payment_type})",
            'updated' => "Payment of {$amount} updated for {$this->gym?->name} ({$this->payment_type})",
            'deleted' => "Payment of {$amount} deleted for {$this->gym?->name} ({$this->payment_type})",
            default => "Payment {$eventName} for {$this->gym?->name} ({$this->payment_type})",
        };
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
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
            get: fn(mixed $value, array $attributes) => Number::currency($attributes['amount'], in: $attributes['currency']),
        );
    }

    /**
     * Scope for completed payments
     */
    #[Scope]
    public function completed(Builder $query)
    {
        return $query->where('status', PaymentStatus::COMPLETED);
    }

    /**
     * Scope for pending payments
     */
    #[Scope]
    public function pending(Builder $query)
    {
        return $query->where('status', PaymentStatus::PENDING);
    }

    /**
     * Scope for failed payments
     */
    #[Scope]
    public function failed(Builder $query)
    {
        return $query->where('status', PaymentStatus::FAILED);
    }

    /**
     * Scope for payments by type
     */
    #[Scope]
    public function byType(Builder $query, string $type)
    {
        return $query->where('payment_type', $type);
    }
}
