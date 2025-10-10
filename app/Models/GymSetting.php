<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }
}
