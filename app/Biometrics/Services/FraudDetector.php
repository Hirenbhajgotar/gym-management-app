<?php

namespace App\Biometrics\Services;

use App\Models\Attendances;
use App\Models\GymMember;

class FraudDetector
{
    public function isDuplicate(GymMember $gymMember): bool
    {
        return Attendances::isCurrentlyCheckedIn(
            $gymMember->id,
            $gymMember->gym_id,
            GymMember::class
        );
    }

    public function isSuspiciousVelocity(GymMember $gymMember): bool
    {
        if (! $gymMember->email) {
            return false;
        }

        $count = Attendances::whereHasMorph(
            'attendanceable',
            [GymMember::class],
            fn ($q) => $q->where('email', $gymMember->email)
        )
            ->where('check_in_result', 'granted')
            ->where('check_in_time', '>=', now()->subMinutes(10))
            ->distinct('gym_id')
            ->count('gym_id');

        return $count >= 2;
    }
}
