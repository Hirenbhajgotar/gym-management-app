<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plan_id
 * @property string $feature_name
 * @property string|null $value
 * @property int|null $order_column
 * @property string|null $brief_description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature whereBriefDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature whereFeatureName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature wherePlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlanFeature whereValue($value)
 *
 * @mixin \Eloquent
 */
class PlanFeature extends Model
{
    protected $fillable = [
        'plan_id',
        'feature_name',
        'value',
        'brief_description',
        'order_column',
    ];
}
