<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PermissionCategory: string implements HasLabel, HasIcon
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
            self::SYSTEM => 'Systems',
            self::COMMUNICATION => 'Communication',
            self::GENERAL => 'General',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::USER => 'heroicon-o-users',
            self::CITY => 'heroicon-o-map-pin',
            self::STATE => 'heroicon-o-building-office-2',
            self::COUNTRY => 'heroicon-o-globe-alt',
            self::PLAN => 'heroicon-o-ticket',
            self::ROLE => 'heroicon-o-shield-check',
            self::PERMISSION => 'heroicon-o-key',
            self::GYM_MEMBER => 'heroicon-o-users',
            self::MEMBERSHIP_PLAN => 'heroicon-o-credit-card',
            self::TRAINER => 'heroicon-o-user',
            self::RECEPTIONIST => 'heroicon-o-identification',
            self::ATTENDANCE => 'heroicon-o-calendar-days',
            self::WIDGETS => 'heroicon-o-presentation-chart-bar',
            self::REPORT => 'heroicon-o-document-chart-bar',
            self::SETTING => 'heroicon-o-wrench-screwdriver',
            self::SYSTEM => 'heroicon-o-rocket-launch',
            self::COMMUNICATION => 'heroicon-o-chat-bubble-left-right',
            self::GENERAL => 'heroicon-o-light-bulb',
        };
    }
}
