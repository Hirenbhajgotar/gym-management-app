<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
 * @property Carbon $expires_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Gym $gym
 *
 * @method static Builder<static>|WabaSetupSession newModelQuery()
 * @method static Builder<static>|WabaSetupSession newQuery()
 * @method static Builder<static>|WabaSetupSession query()
 * @method static Builder<static>|WabaSetupSession whereCompletedAt($value)
 * @method static Builder<static>|WabaSetupSession whereCreatedAt($value)
 * @method static Builder<static>|WabaSetupSession whereExpiresAt($value)
 * @method static Builder<static>|WabaSetupSession whereGymId($value)
 * @method static Builder<static>|WabaSetupSession whereId($value)
 * @method static Builder<static>|WabaSetupSession whereMetaResponse($value)
 * @method static Builder<static>|WabaSetupSession wherePhoneNumberId($value)
 * @method static Builder<static>|WabaSetupSession whereSessionId($value)
 * @method static Builder<static>|WabaSetupSession whereStatus($value)
 * @method static Builder<static>|WabaSetupSession whereUpdatedAt($value)
 * @method static Builder<static>|WabaSetupSession whereWabaId($value)
 *
 * @mixin \Eloquent
 */
class WabaSetupSession extends Model
{
    use HasFactory;
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
    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    // =========================
    // Helper Methods
    // =========================

    public static function createForGym(int $gymId): self
    {
        return self::query()->create([
            'gym_id' => $gymId,
            'session_id' => Str::uuid()->toString(),
            'status' => WabaSetupSessionStatusEnum::PENDING,
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
