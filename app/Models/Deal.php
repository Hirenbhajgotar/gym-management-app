<?php

namespace App\Models;

use App\Enums\DealStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Deal extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'lead_id',
        'membership_plan_id',
        'value',
        'stage',
        'expected_close_date',
    ];

    protected function casts(): array
    {
        return [
            'stage' => DealStage::class,
            'expected_close_date' => 'date',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    public function interactions(): MorphMany
    {
        return $this->morphMany(Interaction::class, 'interactionable');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }
}
