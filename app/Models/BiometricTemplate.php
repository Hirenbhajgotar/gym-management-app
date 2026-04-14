<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gym_member_id
 * @property int $gym_id
 * @property int|null $enrolled_by_device_id
 * @property \App\Enums\BiometricTypeEnum $biometric_type
 * @property string $provider_type
 * @property string|null $template_ref
 * @property float|null $quality_score
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon $enrolled_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BiometricDevice|null $enrolledByDevice
 * @property-read \App\Models\Gym|null $gym
 * @property-read \App\Models\GymMember|null $gymMember
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricTemplate active()
 * @method static \Database\Factories\BiometricTemplateFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricTemplate forGym(int $gymId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricTemplate forGymMember(int $id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricTemplate ofType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricTemplate whereBiometricType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricTemplate whereEnrolledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricTemplate whereEnrolledByDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricTemplate whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricTemplate whereGymMemberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricTemplate whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricTemplate whereProviderType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricTemplate whereQualityScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricTemplate whereTemplateRef($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricTemplate whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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
