<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $subscription_id
 * @property int $plan_feature_id
 * @property string $feature_name
 * @property string $value
 * @property string|null $brief_description
 * @property int $order_column
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PlanFeature $planFeature
 * @property-read Subscription $subscription
 *
 * @method static Builder<static>|SubscriptionFeature newModelQuery()
 * @method static Builder<static>|SubscriptionFeature newQuery()
 * @method static Builder<static>|SubscriptionFeature query()
 * @method static Builder<static>|SubscriptionFeature whereBriefDescription($value)
 * @method static Builder<static>|SubscriptionFeature whereCreatedAt($value)
 * @method static Builder<static>|SubscriptionFeature whereFeatureName($value)
 * @method static Builder<static>|SubscriptionFeature whereId($value)
 * @method static Builder<static>|SubscriptionFeature whereOrderColumn($value)
 * @method static Builder<static>|SubscriptionFeature wherePlanFeatureId($value)
 * @method static Builder<static>|SubscriptionFeature whereSubscriptionId($value)
 * @method static Builder<static>|SubscriptionFeature whereUpdatedAt($value)
 * @method static Builder<static>|SubscriptionFeature whereValue($value)
 *
 * @mixin \Eloquent
 */
class SubscriptionFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'plan_feature_id',
        'feature_name',
        'value',
        'brief_description',
        'order_column',
    ];

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<PlanFeature, $this>
     */
    public function planFeature(): BelongsTo
    {
        return $this->belongsTo(PlanFeature::class);
    }
}
