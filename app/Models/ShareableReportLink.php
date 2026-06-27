<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $gym_id
 * @property int $user_id
 * @property string $token
 * @property string $report_type
 * @property array<array-key, mixed>|null $filters
 * @property Carbon|null $expires_at
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Gym $gym
 * @property-read User $user
 *
 * @method static Builder<static>|ShareableReportLink active()
 * @method static Builder<static>|ShareableReportLink newModelQuery()
 * @method static Builder<static>|ShareableReportLink newQuery()
 * @method static Builder<static>|ShareableReportLink notExpired()
 * @method static Builder<static>|ShareableReportLink query()
 * @method static Builder<static>|ShareableReportLink valid()
 * @method static Builder<static>|ShareableReportLink whereCreatedAt($value)
 * @method static Builder<static>|ShareableReportLink whereExpiresAt($value)
 * @method static Builder<static>|ShareableReportLink whereFilters($value)
 * @method static Builder<static>|ShareableReportLink whereGymId($value)
 * @method static Builder<static>|ShareableReportLink whereId($value)
 * @method static Builder<static>|ShareableReportLink whereIsActive($value)
 * @method static Builder<static>|ShareableReportLink whereReportType($value)
 * @method static Builder<static>|ShareableReportLink whereToken($value)
 * @method static Builder<static>|ShareableReportLink whereUpdatedAt($value)
 * @method static Builder<static>|ShareableReportLink whereUserId($value)
 *
 * @mixin \Eloquent
 */
class ShareableReportLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'user_id',
        'token',
        'report_type',
        'filters',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($link) {
            if (blank($link->token)) {
                $link->token = self::generateUniqueToken();
            }
        });
    }

    public static function generateUniqueToken(int $maxRetries = 10): string
    {
        $count = 0;

        do {
            throw_if($count >= $maxRetries, \RuntimeException::class, 'Failed to generate a unique token after ' . $maxRetries . ' attempts.');

            $token = Str::random(64);
            $count++;
        } while (self::query()->where('token', $token)->exists());

        return $token;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================
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

    // =========================================================================
    // SCOPES
    // =========================================================================

    #[Scope]
    protected function active($query)
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function notExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    #[Scope]
    protected function valid($query)
    {
        return $query->active()->notExpired();
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    public function getShareableUrl(): string
    {
        return route('shared-reports.show', $this->token);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }
}
