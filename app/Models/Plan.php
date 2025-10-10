<?php

namespace App\Models;

use App\Enums\Currency;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'brief_description',
        'price_monthly',
        'price_yearly',
        'currency',
        'is_active',
        'trial_days',
        'badge',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'currency' => Currency::class,
        ];
    }

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /**
     * Check if the plan has a free trial
     */
    public function hasTrial(): bool
    {
        return !is_null($this->trial_days) && $this->trial_days > 0;
    }

    /**
     * Get the trial days for this plan
     */
    public function getTrialDays(): int
    {
        return $this->trial_days ?? 0;
    }

    /**
     * Check if the plan is free (price_monthly = 0)
     */
    public function isFree(): bool
    {
        return $this->price_monthly == 0;
    }

    /**
     * Get a specific feature value by feature name
     */
    public function getFeatureValue(string $featureName): ?string
    {
        $feature = $this->features()->where('feature_name', $featureName)->first();
        return $feature ? $feature->value : null;
    }

    /**
     * Check if a feature is enabled (value is 'true' or a positive number)
     */
    public function hasFeature(string $featureName): bool
    {
        $value = $this->getFeatureValue($featureName);
        return $value === 'true' || (is_numeric($value) && $value > 0) || ($value === '-1');
    }

    /**
     * Get the maximum value for a numeric feature (-1 means unlimited)
     */
    public function getMaxValue(string $featureName): int
    {
        $value = $this->getFeatureValue($featureName);
        return $value === '-1' ? -1 : (int) $value;
    }

    /**
     * Scope for active plans
     */
    #[Scope]
    protected function active(Builder $query)
    {
        return $query->where('is_active', true);
    }
}
