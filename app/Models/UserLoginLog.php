<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $gym_id
 * @property \Illuminate\Support\Carbon $login_at
 * @property \Illuminate\Support\Carbon|null $logged_out_at
 * @property bool $is_current_session
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $device_type
 * @property string $login_method
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Gym $gym
 *
 * @method static \Illuminate\Database\Eloquent\Builder<UserLoginLog> newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<UserLoginLog> newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<UserLoginLog> query()
 * @method static \Illuminate\Database\Eloquent\Builder<UserLoginLog> whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<UserLoginLog> whereDeviceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<UserLoginLog> whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<UserLoginLog> whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<UserLoginLog> whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<UserLoginLog> whereLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<UserLoginLog> whereLoggedOutAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<UserLoginLog> whereLoginMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<UserLoginLog> whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<UserLoginLog> whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<UserLoginLog> whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<UserLoginLog> whereIsCurrentSession($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperUserLoginLog
 */
class UserLoginLog extends Model
{
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

    protected $casts = [
        'login_at' => 'datetime',
        'logged_out_at' => 'datetime',
        'is_current_session' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

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
                $diffInMinutes = $this->login_at->diffInMinutes($endTime);

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
