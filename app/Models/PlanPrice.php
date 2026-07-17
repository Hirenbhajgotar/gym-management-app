<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlanPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $plan_id
 * @property string $currency
 * @property numeric $price_monthly
 * @property numeric $price_yearly
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Plan $plan
 *
 * @method static Builder<static>|PlanPrice newModelQuery()
 * @method static Builder<static>|PlanPrice newQuery()
 * @method static Builder<static>|PlanPrice query()
 *
 * @mixin \Eloquent
 */
class PlanPrice extends Model
{
    /** @use HasFactory<PlanPriceFactory> */
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'currency',
        'price_monthly',
        'price_yearly',
        'effective_from',
        'effective_to',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @param  Builder<PlanPrice>  $query
     * @return Builder<PlanPrice>
     */
    #[Scope]
    protected function effective(Builder $query, Carbon $at): Builder
    {
        return $query->where('effective_from', '<=', $at)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $at));
    }
}
