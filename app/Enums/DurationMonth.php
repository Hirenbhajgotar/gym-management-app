<?php

namespace App\Enums;

enum DurationMonth: int
{
    case THREE_MONTHS = 3;
    case SIX_MONTHS = 6;
    case ONE_YEAR = 12;

    public function getLabel(): string
    {
        return match ($this) {
            self::THREE_MONTHS => '3 Months',
            self::SIX_MONTHS => '6 Months',
            self::ONE_YEAR => '1 Year',
        };
    }
}
