<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gym_id
 * @property string|null $key
 * @property string|null $value
 * @property string|null $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Gym $gym
 *
 * @method static Builder<static>|GymSetting newModelQuery()
 * @method static Builder<static>|GymSetting newQuery()
 * @method static Builder<static>|GymSetting query()
 * @method static Builder<static>|GymSetting whereCreatedAt($value)
 * @method static Builder<static>|GymSetting whereGymId($value)
 * @method static Builder<static>|GymSetting whereId($value)
 * @method static Builder<static>|GymSetting whereKey($value)
 * @method static Builder<static>|GymSetting whereType($value)
 * @method static Builder<static>|GymSetting whereUpdatedAt($value)
 * @method static Builder<static>|GymSetting whereValue($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperGymSetting
 */
class GymSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'key',
        'value',
        'type',
    ];

    protected function value(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                return match ($this->type) {
                    'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                    'integer' => (int) $value,
                    'float' => (float) $value,
                    default => (string) $value,
                };
            },
            set: function ($value) {
                if (is_bool($value)) {
                    return $value ? 'true' : 'false';
                }

                return $value;
            }
        );
    }

    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }
}
