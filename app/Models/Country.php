<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\CountryObserver;
use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $code
 * @property int|null $status 1=Active, 0=Inactive
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, State> $states
 * @property-read int|null $states_count
 *
 * @method static Builder<static>|Country active()
 * @method static CountryFactory factory($count = null, $state = [])
 * @method static Builder<static>|Country inactive()
 * @method static Builder<static>|Country newModelQuery()
 * @method static Builder<static>|Country newQuery()
 * @method static Builder<static>|Country query()
 * @method static Builder<static>|Country whereCode($value)
 * @method static Builder<static>|Country whereCreatedAt($value)
 * @method static Builder<static>|Country whereId($value)
 * @method static Builder<static>|Country whereName($value)
 * @method static Builder<static>|Country whereStatus($value)
 * @method static Builder<static>|Country whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperCountry
 */
#[ObservedBy([CountryObserver::class])]
class Country extends Model
{
    use HasFactory;

    public const COUNTRIES_LIST_CACHE_KEY = 'countries_list';

    protected $fillable = [
        'name',
        'code',
        'status',
    ];

    /**
     * @return HasMany<State, $this>
     */
    public function states(): HasMany
    {
        return $this->hasMany(State::class);
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
