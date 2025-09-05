<?php

namespace App\Models;

use App\Enums\FollowUpStatus;
use App\Enums\InteractionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUp extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'gym_id',
        'user_id',
        'type',
        'scheduled_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => InteractionType::class,
            'scheduled_date' => 'datetime',
            'status' => FollowUpStatus::class,
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}