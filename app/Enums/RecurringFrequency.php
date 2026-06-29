<?php

declare(strict_types=1);

namespace App\Enums;

use DateTimeInterface;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\Date;

enum RecurringFrequency: string implements HasLabel
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case BIWEEKLY = 'biweekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case HALF_YEARLY = 'half_yearly';
    case YEARLY = 'yearly';

    public function getLabel(): string
    {
        return match ($this) {
            self::DAILY => 'Daily',
            self::WEEKLY => 'Weekly',
            self::BIWEEKLY => 'Bi-weekly',
            self::MONTHLY => 'Monthly',
            self::QUARTERLY => 'Quarterly',
            self::HALF_YEARLY => 'Half Yearly',
            self::YEARLY => 'Yearly',
        };
    }

    /**
     * Get options array for Filament select components
     */
    public static function toSelectOptions(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $case) => [$case->value => $case->getLabel()]
        )->all();
    }

    /**
     * Get the number of days for this frequency
     */
    public function getDays(): int
    {
        return match ($this) {
            self::DAILY => 1,
            self::WEEKLY => 7,
            self::BIWEEKLY => 14,
            self::MONTHLY => 30,
            self::QUARTERLY => 90,
            self::HALF_YEARLY => 180,
            self::YEARLY => 365,
        };
    }

    /**
     * Calculate next due date from a given date
     */
    public function getNextDueDate(DateTimeInterface $fromDate): DateTimeInterface
    {
        $date = Date::parse($fromDate);

        return match ($this) {
            self::DAILY => $date->addDay(),
            self::WEEKLY => $date->addWeek(),
            self::BIWEEKLY => $date->addWeeks(2),
            self::MONTHLY => $date->addMonth(),
            self::QUARTERLY => $date->addMonths(3),
            self::HALF_YEARLY => $date->addMonths(6),
            self::YEARLY => $date->addYear(),
        };
    }
}
