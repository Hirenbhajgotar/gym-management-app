<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
