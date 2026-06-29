<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BiometricTypeEnum;
use Database\Factories\BiometricTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $gym_member_id
 * @property int $gym_id
 * @property int|null $enrolled_by_device_id
 * @property BiometricTypeEnum $biometric_type
 * @property string $provider_type
 * @property string|null $template_ref
 * @property float|null $quality_score
 * @property bool $is_active
 * @property Carbon $enrolled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BiometricDevice|null $enrolledByDevice
 * @property-read Gym|null $gym
 * @property-read GymMember|null $gymMember
 *
 * @method static Builder<static>|BiometricTemplate active()
 * @method static BiometricTemplateFactory factory($count = null, $state = [])
 * @method static Builder<static>|BiometricTemplate forGym(int $gymId)
 * @method static Builder<static>|BiometricTemplate forGymMember(int $id)
 * @method static Builder<static>|BiometricTemplate newModelQuery()
 * @method static Builder<static>|BiometricTemplate newQuery()
 * @method static Builder<static>|BiometricTemplate ofType(string $type)
 * @method static Builder<static>|BiometricTemplate query()
 * @method static Builder<static>|BiometricTemplate whereBiometricType($value)
 * @method static Builder<static>|BiometricTemplate whereCreatedAt($value)
 * @method static Builder<static>|BiometricTemplate whereEnrolledAt($value)
 * @method static Builder<static>|BiometricTemplate whereEnrolledByDeviceId($value)
 * @method static Builder<static>|BiometricTemplate whereGymId($value)
 * @method static Builder<static>|BiometricTemplate whereGymMemberId($value)
 * @method static Builder<static>|BiometricTemplate whereId($value)
 * @method static Builder<static>|BiometricTemplate whereIsActive($value)
 * @method static Builder<static>|BiometricTemplate whereProviderType($value)
 * @method static Builder<static>|BiometricTemplate whereQualityScore($value)
 * @method static Builder<static>|BiometricTemplate whereTemplateRef($value)
 * @method static Builder<static>|BiometricTemplate whereUpdatedAt($value)
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
            'biometric_type' => BiometricTypeEnum::class,
        ];
    }

    /**
     * @return BelongsTo<GymMember, $this>
     */
    public function gymMember(): BelongsTo
    {
        return $this->belongsTo(GymMember::class, 'gym_member_id');
    }

    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * @return BelongsTo<BiometricDevice, $this>
     */
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
