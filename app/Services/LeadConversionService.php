<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\GymMember;
use App\Models\Lead;
use App\Models\MembershipPlan;
use Illuminate\Support\Facades\DB;

class LeadConversionService
{
    public function convert(Lead $lead, int $membershipPlanId): GymMember
    {
        return DB::transaction(function () use ($lead, $membershipPlanId) {
            // 1. Create a GymMember from the Lead
            $gymMember = GymMember::create([
                'gym_id' => $lead->gym_id,
                'lead_id' => $lead->id,
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'gym_membership_plan_id' => $membershipPlanId,
                'membership_start' => now(),
                // You might want to add logic for payment status, etc.
            ]);

            // 2. Create a Deal and mark as Closed-Won
            $membershipPlan = MembershipPlan::find($membershipPlanId);
            Deal::create([
                'gym_id' => $lead->gym_id,
                'lead_id' => $lead->id,
                'membership_plan_id' => $membershipPlanId,
                'value' => $membershipPlan->price ?? 0,
                'stage' => \App\Enums\DealStage::CLOSED_WON,
                'expected_close_date' => now(),
            ]);

            // 3. Update the Lead's status
            $lead->status = \App\Enums\LeadStatus::CONVERTED;
            $lead->save();

            // TODO: 4. Dispatch a welcome notification

            return $gymMember;
        });
    }
}
