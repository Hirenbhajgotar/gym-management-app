<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Database\Factories\LeadConversionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $lead_id
 * @property int $gym_member_id
 * @property int $membership_plan_id
 * @property Carbon $conversion_date
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read GymMember $gymMember
 * @property-read Lead $lead
 * @property-read MembershipPlan $membershipPlan
 *
 * @method static LeadConversionFactory factory($count = null, $state = [])
 * @method static Builder<static>|LeadConversion newModelQuery()
 * @method static Builder<static>|LeadConversion newQuery()
 * @method static Builder<static>|LeadConversion query()
 * @method static Builder<static>|LeadConversion whereConversionDate($value)
 * @method static Builder<static>|LeadConversion whereCreatedAt($value)
 * @method static Builder<static>|LeadConversion whereGymMemberId($value)
 * @method static Builder<static>|LeadConversion whereId($value)
 * @method static Builder<static>|LeadConversion whereLeadId($value)
 * @method static Builder<static>|LeadConversion whereMembershipPlanId($value)
 * @method static Builder<static>|LeadConversion whereNotes($value)
 * @method static Builder<static>|LeadConversion whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 * @mixin IdeHelperLeadConversion
 */
class LeadConversion extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'gym_member_id',
        'membership_plan_id',
        'conversion_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'conversion_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<GymMember, $this>
     */
    public function gymMember(): BelongsTo
    {
        return $this->belongsTo(GymMember::class);
    }

    /**
     * @return BelongsTo<MembershipPlan, $this>
     */
    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }
}
