<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\SubscriptionStatus;
use App\Observers\SubscriptionObserver;
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
use Illuminate\Support\Facades\Date;

/**
 * @property int $id
 * @property int $user_id
 * @property int $plan_id
 * @property SubscriptionStatus $status
 * @property string $billing_cycle
 * @property numeric $price
 * @property Currency $currency
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $ends_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $grace_period_ends_at
 * @property array<array-key, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read mixed $days_in_grace_period
 * @property-read Collection<int, SubscriptionFeature> $features
 * @property-read int|null $features_count
 * @property-read Plan $plan
 * @property-read User $user
 *
 * @method static Builder<static>|Subscription active()
 * @method static Builder<static>|Subscription cancelled()
 * @method static Builder<static>|Subscription expired()
 * @method static Builder<static>|Subscription newModelQuery()
 * @method static Builder<static>|Subscription newQuery()
 * @method static Builder<static>|Subscription query()
 * @method static Builder<static>|Subscription suspended()
 * @method static Builder<static>|Subscription whereBillingCycle($value)
 * @method static Builder<static>|Subscription whereCancelledAt($value)
 * @method static Builder<static>|Subscription whereCreatedAt($value)
 * @method static Builder<static>|Subscription whereCurrency($value)
 * @method static Builder<static>|Subscription whereCurrentPeriodEnd($value)
 * @method static Builder<static>|Subscription whereCurrentPeriodStart($value)
 * @method static Builder<static>|Subscription whereEndsAt($value)
 * @method static Builder<static>|Subscription whereGracePeriodEndsAt($value)
 * @method static Builder<static>|Subscription whereId($value)
 * @method static Builder<static>|Subscription whereMetadata($value)
 * @method static Builder<static>|Subscription wherePlanId($value)
 * @method static Builder<static>|Subscription wherePrice($value)
 * @method static Builder<static>|Subscription whereStatus($value)
 * @method static Builder<static>|Subscription whereTrialEndsAt($value)
 * @method static Builder<static>|Subscription whereUpdatedAt($value)
 * @method static Builder<static>|Subscription whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy([SubscriptionObserver::class])]
class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'billing_cycle',
        'price',
        'currency',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'ends_at',
        'cancelled_at',
        'grace_period_ends_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'grace_period_ends_at' => 'datetime',
            'metadata' => 'array',
            'status' => SubscriptionStatus::class,
            'currency' => Currency::class,
        ];
    }

    // Relationships
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<SubscriptionFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(SubscriptionFeature::class);
    }

    // Scopes
    #[Scope]
    protected function active(Builder $query)
    {
        return $query->whereIn('status', [
            SubscriptionStatus::TRIAL,
            SubscriptionStatus::ACTIVE,
        ]);
    }

    #[Scope]
    protected function expired(Builder $query)
    {
        return $query->where('status', SubscriptionStatus::EXPIRED);
    }

    #[Scope]
    protected function cancelled(Builder $query)
    {
        return $query->where('status', SubscriptionStatus::CANCELLED);
    }

    #[Scope]
    protected function suspended(Builder $query)
    {
        return $query->where('status', SubscriptionStatus::SUSPENDED);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function canAccessFeatures(): bool
    {
        // Allow access if status is active or trial
        if ($this->isActive()) {
            // Additional check: if this is a trial, ensure it hasn't expired
            if (
                $this->status === SubscriptionStatus::TRIAL &&
                ($this->trial_ends_at?->isPast() ?? false)
            ) {
                return false;
            }

            return true;
        }

        // Allow access during grace period only for paid subscriptions (not trials)
        if (
            $this->status === SubscriptionStatus::EXPIRED &&
            $this->isInGracePeriod() &&
            ! $this->wasTrial()
        ) {
            return true;
        }

        return false;
    }

    public function isOnTrial(): bool
    {
        return $this->status === SubscriptionStatus::TRIAL &&
            $this->trial_ends_at &&
            ($this->trial_ends_at?->isFuture() ?? false);
    }

    public function isCancelled(): bool
    {
        return $this->status === SubscriptionStatus::CANCELLED &&
            $this->cancelled_at?->isPast();
    }

    public function isExpired(): bool
    {
        return $this->status === SubscriptionStatus::EXPIRED;
    }

    public function isSuspended(): bool
    {
        return $this->status === SubscriptionStatus::SUSPENDED;
    }

    public function trialDaysLeft(): int
    {
        if (! $this->isOnTrial()) {
            return 0;
        }

        return (int) Date::now()->diffInDays($this->trial_ends_at, false);
    }

    public function daysUntilExpiry(): int
    {
        if (! $this->current_period_end) {
            return 0;
        }

        return (int) Date::now()->diffInDays($this->current_period_end, false);
    }

    public function getCurrentPrice(): float
    {
        return $this->billing_cycle === 'yearly'
            ? $this->plan->price_yearly ?? 0
            : $this->plan->price_monthly ?? 0;
    }

    public function getSubscriptionFeature(
        string $featureName,
    ): ?SubscriptionFeature {
        return $this->features()->where('feature_name', $featureName)->first();
    }

    public function getFeatureLimit(string $featureName): mixed
    {
        $feature = $this->getSubscriptionFeature($featureName);

        return $feature?->value;
    }

    public function hasFeature(string $featureName): bool
    {
        return $this->getSubscriptionFeature($featureName) !== null;
    }

    public function canUseFeature(
        string $featureName,
        int $currentUsage = 0,
    ): bool {
        if (! $this->canAccessFeatures()) {
            return false;
        }

        $limit = $this->getFeatureLimit($featureName);

        if ($limit === null || $limit === 'unlimited') {
            return true;
        }

        return $currentUsage < (int) $limit;
    }

    // Grace period methods
    public function isInGracePeriod(): bool
    {
        return $this->grace_period_ends_at?->isFuture() ?? false;
    }

    public function gracePeriodEndsAt(): ?Carbon
    {
        return $this->grace_period_ends_at;
    }

    // Accessor for days_in_grace_period
    protected function daysInGracePeriod(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->isInGracePeriod()
                ? Date::now()->diffInDays($this->grace_period_ends_at, false)
                : 0,
        );
    }

    public function setGracePeriod(int $days): void
    {
        $this->grace_period_ends_at = Date::now()->addDays($days);
        $this->save();
    }

    // Helper method to check if subscription was originally a trial
    public function wasTrial(): bool
    {
        return $this->status !== SubscriptionStatus::TRIAL &&
            $this->trial_ends_at !== null &&
            $this->trial_ends_at->isPast();
    }
}
