<?php

namespace App\Models;

use App\Observers\StateObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $country_id
 * @property string|null $name
 * @property string|null $slug
 * @property int|null $status 1=Active, 0=Inactive
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\City> $cities
 * @property-read int|null $cities_count
 * @property-read \App\Models\Country|null $country
 *
 * @method static Builder<static>|State active()
 * @method static \Database\Factories\StateFactory factory($count = null, $state = [])
 * @method static Builder<static>|State inactive()
 * @method static Builder<static>|State newModelQuery()
 * @method static Builder<static>|State newQuery()
 * @method static Builder<static>|State query()
 * @method static Builder<static>|State whereCountryId($value)
 * @method static Builder<static>|State whereCreatedAt($value)
 * @method static Builder<static>|State whereId($value)
 * @method static Builder<static>|State whereName($value)
 * @method static Builder<static>|State whereSlug($value)
 * @method static Builder<static>|State whereStatus($value)
 * @method static Builder<static>|State whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperState
 */
#[ObservedBy([StateObserver::class])]
class State extends Model
{
    use HasFactory;

    public const STATES_OF_COUNTRY_CACHE_KEY = 'states_of_';

    protected $fillable = [
        'country_id',
        'name',
        'slug',
        'status',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    /**
     * Scope a query to only include active countries.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    /**
     * Scope a query to only include inactive countries.
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 0);
    }
}
