<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plan_id
 * @property string $feature_name
 * @property string|null $value
 * @property int|null $order_column
 * @property string|null $brief_description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|PlanFeature newModelQuery()
 * @method static Builder<static>|PlanFeature newQuery()
 * @method static Builder<static>|PlanFeature query()
 * @method static Builder<static>|PlanFeature whereBriefDescription($value)
 * @method static Builder<static>|PlanFeature whereCreatedAt($value)
 * @method static Builder<static>|PlanFeature whereFeatureName($value)
 * @method static Builder<static>|PlanFeature whereId($value)
 * @method static Builder<static>|PlanFeature whereOrderColumn($value)
 * @method static Builder<static>|PlanFeature wherePlanId($value)
 * @method static Builder<static>|PlanFeature whereUpdatedAt($value)
 * @method static Builder<static>|PlanFeature whereValue($value)
 *
 * @mixin \Eloquent
 */
class PlanFeature extends Model
{
    use HasFactory;
    protected $fillable = [
        'plan_id',
        'feature_name',
        'value',
        'brief_description',
        'order_column',
    ];
}
