<?php

namespace App\Events;

use App\Models\GymMember;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BiometricConsentRevoked
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly GymMember $gymMember,
    ) {}
}
