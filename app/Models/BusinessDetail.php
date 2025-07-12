<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
