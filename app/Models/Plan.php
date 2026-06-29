<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Observers\PlanObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_enterprise
 * @property numeric|null $price_monthly
 * @property numeric|null $price_yearly
 * @property Currency $currency
 * @property bool $is_active
 * @property int|null $trial_days
 * @property string|null $badge
 * @property string|null $description
 * @property string|null $brief_description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PlanFeature> $features
 * @property-read int|null $features_count
 *
 * @method static Builder<static>|Plan active()
 * @method static Builder<static>|Plan newModelQuery()
 * @method static Builder<static>|Plan newQuery()
 * @method static Builder<static>|Plan query()
 * @method static Builder<static>|Plan trial()
 * @method static Builder<static>|Plan whereBadge($value)
 * @method static Builder<static>|Plan whereBriefDescription($value)
 * @method static Builder<static>|Plan whereCreatedAt($value)
 * @method static Builder<static>|Plan whereCurrency($value)
 * @method static Builder<static>|Plan whereDescription($value)
 * @method static Builder<static>|Plan whereId($value)
 * @method static Builder<static>|Plan whereIsActive($value)
 * @method static Builder<static>|Plan whereIsEnterprise($value)
 * @method static Builder<static>|Plan whereName($value)
 * @method static Builder<static>|Plan wherePriceMonthly($value)
 * @method static Builder<static>|Plan wherePriceYearly($value)
 * @method static Builder<static>|Plan whereSlug($value)
 * @method static Builder<static>|Plan whereTrialDays($value)
 * @method static Builder<static>|Plan whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy([PlanObserver::class])]
class Plan extends Model
{
    use HasFactory;

    public const NAVIGATION_BADGE_CACHE_KEY = 'plan_navigation_badge_count';

    public const ALL_PLANS_FOR_HOME_CACHE_KEY = 'all_plans_for_home';

    public const PROFILE_ALL_PLANS_CACHE_KEY = 'profile_all_plans';

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
        'is_enterprise',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_enterprise' => 'boolean',
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'currency' => Currency::class,
        ];
    }

    /**
     * @return HasMany<PlanFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /**
     * Scope for active plans
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function trial(Builder $query): Builder
    {
        return $query->where('trial_days', '>', 0);
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
        return (float) $this->price_monthly === 0.0;
    }

    /**
     * Get a specific feature value by feature name
     */
    public function getFeatureValue(string $featureName): ?string
    {
        /**
         * @var PlanFeature $feature
         */
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
}
