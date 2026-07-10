<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $gym_id
 * @property Carbon $login_at
 * @property Carbon|null $logged_out_at
 * @property bool $is_current_session
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $device_type
 * @property string $login_method
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read mixed $duration_minutes
 * @property-read Gym $gym
 * @property-read mixed $session_duration
 * @property-read User $user
 *
 * @method static Builder<static>|UserLoginLog active()
 * @method static Builder<static>|UserLoginLog forUser(int $userId)
 * @method static Builder<static>|UserLoginLog newModelQuery()
 * @method static Builder<static>|UserLoginLog newQuery()
 * @method static Builder<static>|UserLoginLog query()
 * @method static Builder<static>|UserLoginLog whereCreatedAt($value)
 * @method static Builder<static>|UserLoginLog whereDeviceType($value)
 * @method static Builder<static>|UserLoginLog whereGymId($value)
 * @method static Builder<static>|UserLoginLog whereId($value)
 * @method static Builder<static>|UserLoginLog whereIpAddress($value)
 * @method static Builder<static>|UserLoginLog whereIsCurrentSession($value)
 * @method static Builder<static>|UserLoginLog whereLoggedOutAt($value)
 * @method static Builder<static>|UserLoginLog whereLoginAt($value)
 * @method static Builder<static>|UserLoginLog whereLoginMethod($value)
 * @method static Builder<static>|UserLoginLog whereUpdatedAt($value)
 * @method static Builder<static>|UserLoginLog whereUserAgent($value)
 * @method static Builder<static>|UserLoginLog whereUserId($value)
 *
 * @mixin \Eloquent
 */
class UserLoginLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'gym_id',
        'login_at',
        'logged_out_at',
        'is_current_session',
        'ip_address',
        'user_agent',
        'device_type',
        'login_method',
    ];

    protected function casts(): array
    {
        return [
            'login_at' => 'datetime',
            'logged_out_at' => 'datetime',
            'is_current_session' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    /**
     * Scope to get only currently active sessions.
     */
    #[Scope]
    protected function active($query)
    {
        return $query->where('is_current_session', true)
            ->whereNull('logged_out_at');
    }

    /**
     * Scope to get sessions for a specific user.
     */
    #[Scope]
    protected function forUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get the session duration in minutes/hours.
     */
    protected function sessionDuration(): Attribute
    {
        return Attribute::make(
            get: function () {
                $endTime = $this->logged_out_at ?? now();
                $diffInMinutes = (int) $this->login_at->diffInMinutes($endTime);

                if ($diffInMinutes < 1) {
                    return 'Less than a minute';
                }

                $hours = intdiv($diffInMinutes, 60);
                $minutes = $diffInMinutes % 60;

                return match (true) {
                    $hours > 0 && $minutes > 0 => "{$hours}h {$minutes}m",
                    $hours > 0 => "{$hours}h",
                    default => "{$minutes} mins",
                };
            },
        );
    }

    /**
     * Get the raw duration in minutes.
     */
    protected function durationMinutes(): Attribute
    {
        return Attribute::make(
            get: function () {
                $endTime = $this->logged_out_at ?? now();

                return (int) $this->login_at->diffInMinutes($endTime);
            },
        );
    }

    /**
     * Check if the user is currently logged in (session is active).
     */
    public function isCurrentlyLoggedIn(): bool
    {
        return $this->is_current_session && $this->logged_out_at === null;
    }

    /**
     * Mark this session as logged out.
     */
    public function markAsLoggedOut(): void
    {
        $this->update([
            'logged_out_at' => now(),
            'is_current_session' => false,
        ]);
    }
}
