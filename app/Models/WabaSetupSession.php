<?php

namespace App\Models;

use App\Enums\WabaSetupSessionStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $gym_id
 * @property string $session_id
 * @property string $status
 * @property string|null $waba_id
 * @property string|null $phone_number_id
 * @property string|null $meta_response
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 */
class WabaSetupSession extends Model
{
    protected $fillable = [
        'gym_id',
        'session_id',
        'status',
        'waba_id',
        'phone_number_id',
        'meta_response',
        'expires_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WabaSetupSessionStatusEnum::class,
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // =========================
    // Relationships
    // =========================

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    // =========================
    // Helper Methods
    // =========================

    public static function createForGym(int $gymId): self
    {
        return self::create([
            'gym_id' => $gymId,
            'session_id' => Str::uuid()->toString(),
            'status' => WabaSetupSessionStatusEnum::PENDING->value,
            'expires_at' => now()->addHours(1),
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === WabaSetupSessionStatusEnum::PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === WabaSetupSessionStatusEnum::COMPLETED;
    }

    public function markAsCompleted(array $metaResponse): void
    {
        $this->update([
            'status' => WabaSetupSessionStatusEnum::COMPLETED->value,
            'meta_response' => json_encode($metaResponse),
            'completed_at' => now(),
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update([
            'status' => WabaSetupSessionStatusEnum::EXPIRED->value,
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => WabaSetupSessionStatusEnum::FAILED->value,
        ]);
    }
}
