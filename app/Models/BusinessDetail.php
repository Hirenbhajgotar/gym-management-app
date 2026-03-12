<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $gym_id
 * @property int|null $business_type_id
 * @property string|null $number_of_locations
 * @property string|null $years_in_business
 * @property string|null $website
 * @property string|null $gym_size
 * @property string|null $business_description
 * @property string|null $special_requirements
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BusinessType|null $businessType
 * @property-read \App\Models\Gym|null $gym
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereBusinessDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereBusinessTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereGymSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereNumberOfLocations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereSpecialRequirements($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereWebsite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BusinessDetail whereYearsInBusiness($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperBusinessDetail
 */
class BusinessDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'business_type_id',
        'number_of_locations',
        'years_in_business',
        'website',
        'gym_size',
        'business_description',
        'special_requirements',
    ];

    /**
     * Get the gym that owns the business detail.
     */
    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * Get the business type associated with the business detail.
     */
    public function businessType()
    {
        return $this->belongsTo(BusinessType::class);
    }
}
