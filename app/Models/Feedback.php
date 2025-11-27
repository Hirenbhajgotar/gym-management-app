<?php

namespace App\Models;

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
 * @property \Illuminate\Support\Carbon $submitted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereFeedback($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereFiles($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereSubmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Feedback whereUserId($value)
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
