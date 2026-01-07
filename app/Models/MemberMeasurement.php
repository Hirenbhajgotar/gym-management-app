<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberMeasurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_member_id',
        'weight',
        'height',
        'bmi',
        'measured_at',
        'notes',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
        'bmi' => 'decimal:2',
        'measured_at' => 'date',
    ];

    protected static function booted()
    {
        static::saving(function ($measurement) {
            if ($measurement->weight && $measurement->height) {
                // Height in cm to meters
                $heightInMeters = $measurement->height / 100;
                if ($heightInMeters > 0) {
                    $measurement->bmi = round($measurement->weight / ($heightInMeters * $heightInMeters), 2);
                }
            }
        });
    }

    public function gymMember(): BelongsTo
    {
        return $this->belongsTo(GymMember::class);
    }

    public function status(): Attribute
    {
        return Attribute::make(
            get: function () {
                $bmi = $this->bmi;
                if (! $bmi) {
                    return null;
                }

                return match (true) {
                    $bmi < 18.5 => 'Underweight',
                    $bmi < 25 => 'Normal',
                    $bmi < 30 => 'Overweight',
                    default => 'Obese',
                };
            }
        );
    }

    public function bmi(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->height && $this->weight) {
                    return round($this->weight / (($this->height / 100) ** 2), 1);
                }

                return null;
            }
        );
    }
}
