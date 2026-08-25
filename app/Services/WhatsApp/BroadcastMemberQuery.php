<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\GymMember;
use Illuminate\Database\Eloquent\Builder;

class BroadcastMemberQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<GymMember>
     */
    public static function build(int $gymId, array $filters): Builder
    {
        $query = GymMember::query()
            ->whatsappMarketingAllowed()
            ->where('gym_id', $gymId)
            ->whereNotNull('phone')
            ->where('phone', '!=', '');

        if (isset($filters['membership_type']) && $filters['membership_type']) {
            if ($filters['membership_type'] === 'active') {
                $query->membershipActive();
            }

            if ($filters['membership_type'] === 'inactive') {
                $query->membershipExpired();
            }
        }

        if (isset($filters['plans']) && $filters['plans']) {
            $query->where('gym_membership_plan_id', $filters['plans']);
        }

        if (isset($filters['payment_status']) && $filters['payment_status']) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (isset($filters['trainers']) && $filters['trainers']) {
            $query->where('trainer_id', $filters['trainers']);
        }

        return $query;
    }
}
