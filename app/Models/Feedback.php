<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $rating Rating from 1-5
 * @property string $feedback
 * @property array<array-key, mixed>|null $files
 * @property string|null $email
 * @property Carbon $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 *
 * @method static Builder<static>|Feedback newModelQuery()
 * @method static Builder<static>|Feedback newQuery()
 * @method static Builder<static>|Feedback query()
 * @method static Builder<static>|Feedback whereCreatedAt($value)
 * @method static Builder<static>|Feedback whereEmail($value)
 * @method static Builder<static>|Feedback whereFeedback($value)
 * @method static Builder<static>|Feedback whereFiles($value)
 * @method static Builder<static>|Feedback whereId($value)
 * @method static Builder<static>|Feedback whereRating($value)
 * @method static Builder<static>|Feedback whereSubmittedAt($value)
 * @method static Builder<static>|Feedback whereUpdatedAt($value)
 * @method static Builder<static>|Feedback whereUserId($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperFeedback
 */
class Feedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'rating',
        'feedback',
        'email',
        'files',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'rating' => 'integer',
        'files' => 'array',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
