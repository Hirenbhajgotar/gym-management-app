<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Models\GymMember;
use App\Models\Lead;
use App\Models\LeadConversion;
use App\Traits\HasTenantAccess;
use Exception;
use Illuminate\Support\Facades\DB;

class LeadConversionService
{
    use HasTenantAccess;

    public function convert(Lead $lead, array $extraData = []): GymMember
    {
        return DB::transaction(function () use ($lead, $extraData) {
            $lockedLead = $lead->lockForUpdate()->find($lead->id);

            throw_unless($lockedLead, Exception::class, 'Lead not found.');

            throw_if($lockedLead->status === LeadStatus::CONVERTED, Exception::class, 'Lead has already been converted.');

            $gymId = $this->getGymId();
            $now = now();

            // Prepare Member Data
            $memberData = [
                'gym_id' => $gymId,
                'name' => $lockedLead->name,
                'email' => $lockedLead->email,
                'phone' => $lockedLead->phone,
                'gym_membership_plan_id' => $extraData['membership_plan_id'] ?? null,
                'trainer_id' => $extraData['trainer_id'] ?? null,
                'membership_start' => $extraData['membership_start'] ?? $now,
                'membership_end' => $extraData['membership_end'] ?? null,
                'payment_status' => $extraData['status'] ?? PaymentStatus::PENDING->value,
            ];

            // 1. Create a GymMember from the Lead
            $gymMember = GymMember::query()->create($memberData);

            // 2. Create the LeadConversion record
            LeadConversion::query()->create([
                'lead_id' => $lockedLead->id,
                'gym_member_id' => $gymMember->id,
                'membership_plan_id' => $extraData['membership_plan_id'] ?? null,
                'conversion_date' => $extraData['conversion_date'] ?? $now,
                'notes' => $extraData['conversion_notes'] ?? null,
            ]);

            // 3. Create Payment Record if amount is present
            if (isset($extraData['amount'])) {
                $paymentDate = $extraData['payment_date'] ?? $now;

                $payment = $gymMember->payments()->create([
                    'gym_id' => $gymId,
                    'membership_plan_id' => $extraData['membership_plan_id'] ?? null,
                    'amount' => $extraData['amount'],
                    'currency' => $extraData['currency'] ?? 'INR',
                    'payment_method' => $extraData['payment_method'] ?? 'cash',
                    'status' => $extraData['status'] ?? PaymentStatus::PENDING->value,
                    'notes' => $extraData['notes'] ?? null,
                    'payment_date' => $paymentDate,
                    'membership_start' => $extraData['membership_start'] ?? null,
                    'membership_end' => $extraData['membership_end'] ?? null,
                ]);

                // Update latest payment date on member if payment is successful/recorded
                if ($payment) {
                    $gymMember->update(['latest_payment_at' => $paymentDate]);
                }
            }

            // 4. Update the Lead's status
            $lockedLead->update([
                'status' => LeadStatus::CONVERTED,
                'membership_plan_id' => $gymMember->gym_membership_plan_id ?? null,
            ]);

            return $gymMember;
        }, 3);
    }
}
