<?php

namespace App\Models;

use App\Enums\WabaMigrationStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gym_id
 * @property int $whatsapp_account_id
 * @property WabaMigrationStatusEnum $status
 * @property string|null $old_waba_id
 * @property string|null $old_waba_mode
 * @property string|null $new_waba_id
 * @property string|null $new_waba_mode
 * @property string|null $migration_notes
 * @property array<array-key, mixed>|null $migration_data
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $failed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\WhatsAppAccount $whatsappAccount
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaMigration newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaMigration newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaMigration query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaMigration whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaMigration whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaMigration whereFailedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaMigration whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaMigration whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaMigration whereMigrationData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaMigration whereMigrationNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaMigration whereNewWabaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaMigration whereNewWabaMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaMigration whereOldWabaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaMigration whereOldWabaMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaMigration whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaMigration whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaMigration whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WabaMigration whereWhatsappAccountId($value)
 *
 * @mixin \Eloquent
 */
class WabaMigration extends Model
{
    protected $fillable = [
        'gym_id',
        'whatsapp_account_id',
        'status',
        'old_waba_id',
        'old_waba_mode',
        'new_waba_id',
        'new_waba_mode',
        'migration_notes',
        'migration_data',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WabaMigrationStatusEnum::class,
            'migration_data' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    // =========================
    // Relationships
    // =========================

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }

    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    // =========================
    // Helper Methods
    // =========================

    public function isPending(): bool
    {
        return $this->status === WabaMigrationStatusEnum::PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === WabaMigrationStatusEnum::IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === WabaMigrationStatusEnum::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === WabaMigrationStatusEnum::FAILED;
    }

    public function markAsStarted(): void
    {
        $this->update([
            'status' => WabaMigrationStatusEnum::IN_PROGRESS->value,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(string $newWabaId, string $newWabaMode): void
    {
        $this->update([
            'status' => WabaMigrationStatusEnum::COMPLETED->value,
            'new_waba_id' => $newWabaId,
            'new_waba_mode' => $newWabaMode,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $notes): void
    {
        $this->update([
            'status' => WabaMigrationStatusEnum::FAILED->value,
            'migration_notes' => $notes,
            'failed_at' => now(),
        ]);
    }

    public static function createForGym(int $gymId, int $whatsappAccountId, string $oldWabaId, string $oldWabaMode): self
    {
        return self::create([
            'gym_id' => $gymId,
            'whatsapp_account_id' => $whatsappAccountId,
            'status' => WabaMigrationStatusEnum::PENDING->value,
            'old_waba_id' => $oldWabaId,
            'old_waba_mode' => $oldWabaMode,
        ]);
    }
}
