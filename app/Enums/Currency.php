<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Currency: string implements HasLabel
{
    case USD = 'USD';
    case INR = 'INR';

    public function getLabel(): string
    {
        return match ($this) {
            self::USD => 'United States Dollar (USD)',
            self::INR => 'Indian Rupee (INR)',
        };
    }

    public function getSymbol(): string
    {
        return match ($this) {
            self::USD => '$',
            self::INR => '₹',
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
}
