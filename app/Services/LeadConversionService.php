<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\GymMember;
use App\Models\Lead;
use App\Models\LeadConversion;
use App\Traits\HasTenantAccess;
use Illuminate\Support\Facades\DB;

class LeadConversionService
{
    use HasTenantAccess;

    public function convert(Lead $lead, array $extraData = []): GymMember
    {
        return DB::transaction(function () use ($lead, $extraData) {
            // 1. Create a GymMember from the Lead
            $gymMember = GymMember::create([
                'gym_id' => $this->getGymId(),
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'gym_membership_plan_id' => $extraData['membership_plan_id'] ?? null,
                'membership_start' => now(),
            ]);

            // 2. Create the LeadConversion record
            LeadConversion::create([
                'lead_id' => $lead->id,
                'gym_member_id' => $gymMember->id,
                'membership_plan_id' => $extraData['membership_plan_id'],
                'conversion_date' => $extraData['conversion_date'],
                'notes' => $extraData['notes'],
            ]);

            // 3. Update the Lead's status
            $lead->status = LeadStatus::CONVERTED;
            $lead->save();

            return $gymMember;
        });
    }
}
