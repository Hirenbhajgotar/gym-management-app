<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $subscription_id
 * @property int $plan_feature_id
 * @property string $feature_name
 * @property string $value
 * @property string|null $brief_description
 * @property int $order_column
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\PlanFeature $planFeature
 * @property-read \App\Models\Subscription $subscription
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature whereBriefDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature whereFeatureName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature wherePlanFeatureId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature whereSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionFeature whereValue($value)
 * @mixin \Eloquent
 */
class SubscriptionFeature extends Model
{
    protected $fillable = [
        'subscription_id',
        'plan_feature_id',
        'feature_name',
        'value',
        'brief_description',
        'order_column',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function planFeature(): BelongsTo
    {
        return $this->belongsTo(PlanFeature::class);
    }
}
