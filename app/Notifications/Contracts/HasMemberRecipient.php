<?php

declare(strict_types=1);

namespace App\Notifications\Contracts;

use App\Models\GymMember;

interface HasMemberRecipient
{
    public function getMember(): ?GymMember;
}
