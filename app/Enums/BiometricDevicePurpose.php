<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BiometricDevicePurpose: string implements HasColor, HasLabel
{
    case AttendanceOnly = 'attendance_only';
    case DoorAccess = 'door_access';
    case DoorAccessNoAttendance = 'door_access_no_attendance';

    public function getLabel(): string
    {
        return match ($this) {
            self::AttendanceOnly => 'Attendance Only',
            self::DoorAccess => 'Door Access + Attendance',
            self::DoorAccessNoAttendance => 'Door Access Only',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::AttendanceOnly => 'blue',
            self::DoorAccess => 'success',
            self::DoorAccessNoAttendance => 'orange',
        };
    }
}
