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
/**
 * @property int $id
 * @property int $gym_member_id
 * @property numeric $weight
 * @property numeric $height
 * @property numeric|null $bmi
 * @property \Illuminate\Support\Carbon $measured_at
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\GymMember $gymMember
 * @property-read mixed $status
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberMeasurement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberMeasurement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberMeasurement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberMeasurement whereBmi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberMeasurement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberMeasurement whereGymMemberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberMeasurement whereHeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberMeasurement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberMeasurement whereMeasuredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberMeasurement whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberMeasurement whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberMeasurement whereWeight($value)
 *
 * @mixin \Eloquent
 */
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

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'height' => 'decimal:2',
            'bmi' => 'decimal:2',
            'measured_at' => 'date',
        ];
    }

    protected static function booted()
    {
        static::saving(function ($measurement) {
            if ($measurement->weight && $measurement->height) {
                $measurement->bmi = BmiHelper::calculateBmi($measurement->weight, $measurement->height);
            }
        });
    }

    /**
     * @return BelongsTo<GymMember, $this>
     */
    public function gymMember(): BelongsTo
    {
        return $this->belongsTo(GymMember::class);
    }

    protected function status(): Attribute
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
