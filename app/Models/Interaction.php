<?php

namespace App\Models;

use App\Enums\InteractionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Interaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'user_id',
        'interactionable_id',
        'interactionable_type',
        'type',
        'notes',
        'interaction_date',
    ];

    protected function casts(): array
    {
        return [
            'type' => InteractionType::class,
            'interaction_date' => 'datetime',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function interactionable(): MorphTo
    {
        return $this->morphTo();
    }
}
