<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
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
                'trainer_id' => $extraData['trainer_id'] ?? null,
                'membership_start' => $extraData['membership_start'] ?? now(),
                'membership_end' => $extraData['membership_end'] ?? null,
                'payment_status' => $extraData['status'] ?? PaymentStatus::PENDING->value,
            ]);

            // 2. Create the LeadConversion record
            LeadConversion::create([
                'lead_id' => $lead->id,
                'gym_member_id' => $gymMember->id,
                'membership_plan_id' => $extraData['membership_plan_id'],
                'conversion_date' => $extraData['conversion_date'] ?? now(),
                'notes' => $extraData['conversion_notes'] ?? null,
            ]);

            // 3. Create Payment Record if amount is present
            if (isset($extraData['amount'])) {
                $payment = $gymMember->payments()->create([
                    'gym_id' => $this->getGymId(),
                    'membership_plan_id' => $extraData['membership_plan_id'] ?? null,
                    'amount' => $extraData['amount'],
                    'currency' => $extraData['currency'] ?? 'INR',
                    'payment_method' => $extraData['payment_method'] ?? 'cash',
                    'status' => $extraData['status'] ?? PaymentStatus::PENDING->value,
                    'notes' => $extraData['notes'] ?? null,
                    'payment_date' => $extraData['payment_date'] ?? now(),
                    'membership_start' => $extraData['membership_start'] ?? null,
                    'membership_end' => $extraData['membership_end'] ?? null,
                ]);

                // Update latest payment date on member if payment is successful/recorded
                if ($payment) {
                    $gymMember->update(['latest_payment_at' => $extraData['payment_date'] ?? now()]);
                }
            }

            // 4. Update the Lead's status
            $lead->status = LeadStatus::CONVERTED;
            $lead->membership_plan_id = $gymMember->gym_membership_plan_id ?? null;
            $lead->save();

            return $gymMember;
        });
    }
}
