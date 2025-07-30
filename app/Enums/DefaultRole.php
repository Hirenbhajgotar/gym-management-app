<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DefaultRole: string implements HasLabel
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case GYM_OWNER = 'gym_owner';
    case MANAGER = 'manager';
    case RECEPTIONIST = 'receptionist';
    case TRAINER = 'trainer';
    case GYM_MEMBER = 'gym_member';
    case TESTER = 'tester';

    public function getLabel(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::GYM_OWNER => 'Gym Owner',
            self::RECEPTIONIST => 'Receptionist',
            self::MANAGER => 'Manager',
            self::TRAINER => 'Trainer',
            self::GYM_MEMBER => 'Member',
        };
    }

    /**
     * Check if the role is tenant-specific (scoped to a gym).
     */
    public function isTenantRole(): bool
    {
        return in_array($this, [
            self::GYM_OWNER,
            self::MANAGER,
            self::RECEPTIONIST,
            self::TRAINER,
            self::GYM_MEMBER,
        ]);
    }

    /**
     * Get all tenant-specific roles.
     *
     * @return array<int, self>
     */
    public static function tenantRoles(): array
    {
        return [
            self::GYM_OWNER,
            self::MANAGER,
            self::RECEPTIONIST,
            self::TRAINER,
            self::GYM_MEMBER,
        ];
    }

    /**
     * Get all global roles.
     *
     * @return array<int, self>
     */
    public static function globalRoles(): array
    {
        return [
            self::SUPER_ADMIN,
            // self::GYM_OWNER,
        ];
    }
}
