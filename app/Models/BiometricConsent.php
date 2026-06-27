<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $gym_member_id
 * @property int $gym_id
 * @property string $ip_address
 * @property string $version
 * @property Carbon $consented_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Gym $gym
 * @property-read GymMember $gymMember
 *
 * @method static Builder<static>|BiometricConsent newModelQuery()
 * @method static Builder<static>|BiometricConsent newQuery()
 * @method static Builder<static>|BiometricConsent query()
 * @method static Builder<static>|BiometricConsent whereConsentedAt($value)
 * @method static Builder<static>|BiometricConsent whereCreatedAt($value)
 * @method static Builder<static>|BiometricConsent whereGymId($value)
 * @method static Builder<static>|BiometricConsent whereGymMemberId($value)
 * @method static Builder<static>|BiometricConsent whereId($value)
 * @method static Builder<static>|BiometricConsent whereIpAddress($value)
 * @method static Builder<static>|BiometricConsent whereRevokedAt($value)
 * @method static Builder<static>|BiometricConsent whereUpdatedAt($value)
 * @method static Builder<static>|BiometricConsent whereVersion($value)
 *
 * @mixin \Eloquent
 */
class BiometricConsent extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_member_id',
        'gym_id',
        'ip_address',
        'version',
        'consented_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'revoked_at' => 'datetime',
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

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
