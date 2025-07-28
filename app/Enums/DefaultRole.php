<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DefaultRole: string implements HasLabel
{
    case ADMIN = 'admin';
    case GYM_OWNER = 'gym_owner';
    case RECEPTIONIST = 'receptionist';
    case MANAGER = 'manager';
    case SUPER_ADMIN = 'super_admin';
    case TRAINER = 'trainer';

    public function getLabel(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::GYM_OWNER => 'Gym Owner',
            self::RECEPTIONIST => 'Receptionist',
            self::MANAGER => 'Manager',
            self::TRAINER => 'Trainer',
        };
    }
}
