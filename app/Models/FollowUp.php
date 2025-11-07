<?php

namespace App\Models;

use App\Enums\FollowUpStatus;
use App\Enums\InteractionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $lead_id
 * @property int $gym_id
 * @property int $user_id Staff who performed the follow-up
 * @property InteractionType $type
 * @property \Illuminate\Support\Carbon $scheduled_date
 * @property FollowUpStatus $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\Lead $lead
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\FollowUpFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereLeadId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereScheduledDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FollowUp whereUserId($value)
 * @mixin \Eloquent
 * @mixin IdeHelperFollowUp
 */
class FollowUp extends Model
{
    use HasFactory;

    protected $fillable = [
        "lead_id",
        "gym_id",
        "user_id",
        "type",
        "scheduled_date",
        "status",
        "notes",
    ];

    protected function casts(): array
    {
        return [
            "type" => InteractionType::class,
            "scheduled_date" => "datetime",
            "status" => FollowUpStatus::class,
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
