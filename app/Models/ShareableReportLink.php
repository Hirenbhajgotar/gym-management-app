<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ShareableReportLink extends Model
{
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
            if (empty($link->token)) {
                $link->token = self::generateUniqueToken();
            }
        });
    }

    public static function generateUniqueToken(int $maxRetries = 10): string
    {
        $count = 0;

        do {
            if ($count >= $maxRetries) {
                throw new \RuntimeException('Failed to generate a unique token after ' . $maxRetries . ' attempts.');
            }

            $token = Str::random(64);
            $count++;
        } while (self::where('token', $token)->exists());

        return $token;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

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
