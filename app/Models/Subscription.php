<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

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
            'metadata' => 'array',
            'status' => SubscriptionStatus::class,
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    // Scopes
    #[Scope]
    protected function active(Builder $query)
    {
        return $query->whereIn('status', [SubscriptionStatus::TRIAL, SubscriptionStatus::ACTIVE]);
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
        return $this->status->canAccessFeatures();
    }

    public function isOnTrial(): bool
    {
        return $this->status === SubscriptionStatus::TRIAL;
    }

    public function isCancelled(): bool
    {
        return $this->status === SubscriptionStatus::CANCELLED;
    }

    public function isExpired(): bool
    {
        return $this->status === SubscriptionStatus::EXPIRED;
    }

    public function isSuspended(): bool
    {
        return $this->status === SubscriptionStatus::SUSPENDED;
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function trialDaysLeft(): int
    {
        if (!$this->onTrial()) {
            return 0;
        }

        return Carbon::now()->diffInDays($this->trial_ends_at, false);
    }

    public function daysUntilExpiry(): int
    {
        if (!$this->current_period_end) {
            return 0;
        }

        return Carbon::now()->diffInDays($this->current_period_end, false);
    }

    public function getCurrentPrice(): float
    {
        return $this->billing_cycle === 'yearly'
            ? $this->plan->price_yearly ?? 0
            : $this->plan->price_monthly ?? 0;
    }

    public function getPlanFeature(string $featureName): ?PlanFeature
    {
        return $this->plan->features()->where('feature_name', $featureName)->first();
    }

    public function getFeatureLimit(string $featureName): int|string|null
    {
        $feature = $this->getPlanFeature($featureName);
        return $feature?->value;
    }

    public function hasFeature(string $featureName): bool
    {
        return $this->getPlanFeature($featureName) !== null;
    }

    public function canUseFeature(string $featureName, int $currentUsage = 0): bool
    {
        if (!$this->canAccessFeatures()) {
            return false;
        }

        $limit = $this->getFeatureLimit($featureName);

        if ($limit === null || $limit === 'unlimited') {
            return true;
        }

        return $currentUsage < (int) $limit;
    }
}
