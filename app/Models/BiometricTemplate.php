<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_member_id',
        'gym_id',
        'enrolled_by_device_id',
        'biometric_type',
        'provider_type',
        'template_ref',
        'quality_score',
        'is_active',
        'enrolled_at',
    ];

    protected function casts(): array
    {
        return [
            'template_ref' => 'encrypted',
            'quality_score' => 'float',
            'is_active' => 'boolean',
            'enrolled_at' => 'datetime',
            'biometric_type' => \App\Enums\BiometricTypeEnum::class,
        ];
    }

    public function gymMember(): BelongsTo
    {
        return $this->belongsTo(GymMember::class, 'gym_member_id');
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function enrolledByDevice(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'enrolled_by_device_id');
    }

    #[Scope]
    protected function active($query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function forGymMember($query, int $id): void
    {
        $query->where('gym_member_id', $id);
    }

    #[Scope]
    protected function ofType($query, string $type): void
    {
        $query->where('biometric_type', $type);
    }

    #[Scope]
    protected function forGym($query, int $gymId): void
    {
        $query->where('gym_id', $gymId);
    }
}
