<?php

namespace App\Models;

use App\Enums\Currency;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property numeric|null $price_monthly
 * @property numeric|null $price_yearly
 * @property Currency $currency
 * @property bool $is_active
 * @property int|null $trial_days
 * @property string|null $badge
 * @property string|null $description
 * @property string|null $brief_description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PlanFeature> $features
 * @property-read int|null $features_count
 * @method static Builder<static>|Plan active()
 * @method static Builder<static>|Plan newModelQuery()
 * @method static Builder<static>|Plan newQuery()
 * @method static Builder<static>|Plan query()
 * @method static Builder<static>|Plan whereBadge($value)
 * @method static Builder<static>|Plan whereBriefDescription($value)
 * @method static Builder<static>|Plan whereCreatedAt($value)
 * @method static Builder<static>|Plan whereCurrency($value)
 * @method static Builder<static>|Plan whereDescription($value)
 * @method static Builder<static>|Plan whereId($value)
 * @method static Builder<static>|Plan whereIsActive($value)
 * @method static Builder<static>|Plan whereName($value)
 * @method static Builder<static>|Plan wherePriceMonthly($value)
 * @method static Builder<static>|Plan wherePriceYearly($value)
 * @method static Builder<static>|Plan whereSlug($value)
 * @method static Builder<static>|Plan whereTrialDays($value)
 * @method static Builder<static>|Plan whereUpdatedAt($value)
 * @mixin \Eloquent
 * @mixin IdeHelperPlan
 */
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
        return ! is_null($this->trial_days) && $this->trial_days > 0;
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
