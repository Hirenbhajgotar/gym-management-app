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
 * @property WabaSetupSessionStatusEnum $status
 * @property string|null $meta_response
 * @property string|null $waba_id
 * @property string|null $phone_number_id
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaSetupSession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaSetupSession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaSetupSession query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaSetupSession whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaSetupSession whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaSetupSession whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaSetupSession whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaSetupSession whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaSetupSession whereMetaResponse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaSetupSession wherePhoneNumberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaSetupSession whereSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaSetupSession whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaSetupSession whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaSetupSession whereWabaId($value)
 * @mixin \Eloquent
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
