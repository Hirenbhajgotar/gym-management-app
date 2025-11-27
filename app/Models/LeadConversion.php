<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $lead_id
 * @property int $gym_member_id
 * @property int $membership_plan_id
 * @property \Illuminate\Support\Carbon $conversion_date
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\GymMember $gymMember
 * @property-read \App\Models\Lead $lead
 * @property-read \App\Models\MembershipPlan $membershipPlan
 * @method static \Database\Factories\LeadConversionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion whereConversionDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion whereGymMemberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion whereLeadId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion whereMembershipPlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeadConversion whereUpdatedAt($value)
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

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function gymMember(): BelongsTo
    {
        return $this->belongsTo(GymMember::class);
    }

    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }
}
