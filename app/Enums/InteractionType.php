<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InteractionType: string implements HasLabel
{
    case CALL = 'call';
    case EMAIL = 'email';
    case TOUR = 'tour';
    case MEETING = 'meeting';

    public function getLabel(): string
    {
        return match ($this) {
            self::CALL => 'Call',
            self::EMAIL => 'Email',
            self::TOUR => 'Tour',
            self::MEETING => 'Meeting',
        };
    }
}
