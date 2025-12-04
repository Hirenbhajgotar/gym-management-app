<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $code
 * @property int|null $status 1=Active, 0=Inactive
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\State> $states
 * @property-read int|null $states_count
 * @method static Builder<static>|Country active()
 * @method static \Database\Factories\CountryFactory factory($count = null, $state = [])
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
 * @mixin \Eloquent
 * @mixin IdeHelperCountry
 */
class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'status',
    ];

    public function states(): HasMany
    {
        return $this->hasMany(State::class);
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
