<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Database\Factories\CityFactory;
use App\Observers\CityObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $state_id
 * @property string|null $name
 * @property string|null $slug
 * @property int|null $status 1=Active, 0=Inactive
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read State|null $state
 *
 * @method static Builder<static>|City active()
 * @method static CityFactory factory($count = null, $state = [])
 * @method static Builder<static>|City inactive()
 * @method static Builder<static>|City newModelQuery()
 * @method static Builder<static>|City newQuery()
 * @method static Builder<static>|City query()
 * @method static Builder<static>|City whereCreatedAt($value)
 * @method static Builder<static>|City whereId($value)
 * @method static Builder<static>|City whereName($value)
 * @method static Builder<static>|City whereSlug($value)
 * @method static Builder<static>|City whereStateId($value)
 * @method static Builder<static>|City whereStatus($value)
 * @method static Builder<static>|City whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperCity
 */
#[ObservedBy([CityObserver::class])]
class City extends Model
{
    use HasFactory;

    public const CITIES_OF_STATE_CACHE_KEY = 'cities_of_';

    protected $fillable = [
        'state_id',
        'name',
        'slug',
        'status',
    ];

    /**
     * @return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * Scope a query to only include active countries.
     */
    protected function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    /**
     * Scope a query to only include inactive countries.
     */
    protected function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 0);
    }
}
