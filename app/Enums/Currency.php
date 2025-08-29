<?php

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
}
