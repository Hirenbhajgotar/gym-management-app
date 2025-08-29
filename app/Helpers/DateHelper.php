<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
    /**
     * Calculate age from a given date of birth.
     */
    public static function calculateAge(string $dob): int
    {
        return Carbon::parse($dob)->age;
    }
}
