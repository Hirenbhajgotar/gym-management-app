<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gym_member_id
 * @property int $gym_id
 * @property string $ip_address
 * @property string $version
 * @property \Illuminate\Support\Carbon $consented_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Gym $gym
 * @property-read \App\Models\GymMember $gymMember
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricConsent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricConsent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricConsent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricConsent whereConsentedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricConsent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricConsent whereGymId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricConsent whereGymMemberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricConsent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricConsent whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricConsent whereRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricConsent whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BiometricConsent whereVersion($value)
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

    public function gymMember(): BelongsTo
    {
        return $this->belongsTo(GymMember::class, 'gym_member_id');
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
