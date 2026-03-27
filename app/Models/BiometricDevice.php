<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class BiometricDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'device_name',
        'device_type',
        'provider_type',
        'provider_config',
        'status',
        'assigned_staff_id',
        'offline_capable',
        'supports_multi_modal',
        'last_heartbeat_at',
        'firmware_version',
        'device_token',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'provider_config' => 'encrypted:array',
            'offline_capable' => 'boolean',
            'supports_multi_modal' => 'boolean',
            'last_heartbeat_at' => 'datetime',
            'device_token' => 'hashed',
            'status' => \App\Enums\BiometricDeviceStatusEnum::class,
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(BiometricTemplate::class, 'enrolled_by_device_id');
    }

    #[Scope]
    protected function active($query): void
    {
        $query->where('status', 'active');
    }

    #[Scope]
    protected function forGym($query, int $gymId): void
    {
        $query->where('gym_id', $gymId);
    }

    public function verifyToken(string $plainToken): bool
    {
        return $this->device_token !== null && Hash::check($plainToken, $this->device_token);
    }
}
