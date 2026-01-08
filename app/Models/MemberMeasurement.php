<?php

namespace App\Models;

use App\Helpers\BmiHelper;
use App\Observers\MemberMeasurementObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([MemberMeasurementObserver::class])]
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
                $measurement->bmi = BmiHelper::calculateBmi($measurement->weight, $measurement->height);
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

                return BmiHelper::getStatus($bmi);
            }
        );
    }
}
