<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PermissionCategory: string implements HasLabel
{
    case USER = 'user';
    case CITY = 'city';
    case STATE = 'state';
    case COUNTRY = 'country';
    case PLAN = 'plan';
    case ROLE = 'role';
    case PERMISSION = 'permission';
    case GYM_MEMBER = 'gym_member';
    case MEMBERSHIP_PLAN = 'membership_plan';
    case TRAINER = 'trainer';
    case RECEPTIONIST = 'receptionist';
    case ATTENDANCE = 'attendance';
    case WIDGETS = 'widgets';
    case REPORT = 'report';
    case SETTING = 'setting';
    case SYSTEM = 'system';
    case COMMUNICATION = 'communication';
    case GENERAL = 'general';

    public function getLabel(): string
    {
        return match ($this) {
            self::USER => 'User Management',
            self::CITY => 'City Management',
            self::STATE => 'State Management',
            self::COUNTRY => 'Country Management',
            self::PLAN => 'Plan Management',
            self::ROLE => 'Role Management',
            self::PERMISSION => 'Permission Management',
            self::GYM_MEMBER => 'Gym Member Management',
            self::MEMBERSHIP_PLAN => 'Membership Plan Management',
            self::TRAINER => 'Trainer Management',
            self::RECEPTIONIST => 'Receptionist Management',
            self::ATTENDANCE => 'Attendance Management',
            self::WIDGETS => 'Widgets',
            self::REPORT => 'Reports',
            self::SETTING => 'Settings',
            self::SYSTEM => 'systems',
            self::COMMUNICATION => 'Communication',
            self::GENERAL => 'General',
        };
    }
}
