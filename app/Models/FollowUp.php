<?php

namespace App\Models;

use App\Enums\FollowUpStatus;
use App\Enums\InteractionType;
use Database\Factories\FollowUpFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lead_id
 * @property int $gym_id
 * @property int $user_id Staff who performed the follow-up
 * @property InteractionType $type
 * @property Carbon $scheduled_date
 * @property FollowUpStatus $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Gym $gym
 * @property-read Lead $lead
 * @property-read User $user
 *
 * @method static FollowUpFactory factory($count = null, $state = [])
 * @method static Builder<static>|FollowUp newModelQuery()
 * @method static Builder<static>|FollowUp newQuery()
 * @method static Builder<static>|FollowUp query()
 * @method static Builder<static>|FollowUp whereCreatedAt($value)
 * @method static Builder<static>|FollowUp whereGymId($value)
 * @method static Builder<static>|FollowUp whereId($value)
 * @method static Builder<static>|FollowUp whereLeadId($value)
 * @method static Builder<static>|FollowUp whereNotes($value)
 * @method static Builder<static>|FollowUp whereScheduledDate($value)
 * @method static Builder<static>|FollowUp whereStatus($value)
 * @method static Builder<static>|FollowUp whereType($value)
 * @method static Builder<static>|FollowUp whereUpdatedAt($value)
 * @method static Builder<static>|FollowUp whereUserId($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperFollowUp
 */
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

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
