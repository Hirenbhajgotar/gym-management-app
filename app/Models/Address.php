<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $addressable_type
 * @property int $addressable_id
 * @property string|null $street
 * @property int|null $city_id
 * @property int|null $state_id
 * @property int|null $country_id
 * @property string|null $postal_code
 * @property string|null $latitude
 * @property string|null $longitude
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent $addressable
 * @property-read \App\Models\City|null $city
 * @property-read \App\Models\Country|null $country
 * @property-read mixed $full_address
 * @property-read \App\Models\State|null $state
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereAddressableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereAddressableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereCityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereCountryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address wherePostalCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereStateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereStreet($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address withoutTrashed()
 * @mixin \Eloquent
 * @mixin IdeHelperAddress
 */
class Address extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'street',
        'city_id',
        'state_id',
        'country_id',
        'postal_code',
        'latitude',
        'longitude',
    ];

    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    protected function fullAddress(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $address = $this->street . ', ' . $this->postal_code . ' ' . $this->city?->name;

                return $address;
            },
        );
    }
}
