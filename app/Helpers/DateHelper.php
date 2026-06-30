<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Support\Facades\Date;

class DateHelper
{
    /**
     * Calculate age from a given date of birth.
     */
    public static function calculateAge(string $dob): int
    {
        return Date::parse($dob)->age;
    }
}
