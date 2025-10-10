<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
