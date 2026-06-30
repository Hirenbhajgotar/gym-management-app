<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WhatsAppCreditTransactionEnum: string implements HasColor, HasLabel
{
    case PURCHASE = 'purchase';
    case USAGE = 'usage';
    case ADJUSTMENT = 'adjustment';
    case REFUND = 'refund';
    case EXPIRED = 'expired';
    case BONUS = 'bonus';

    public function getLabel(): string
    {
        return match ($this) {
            self::PURCHASE => 'Purchase',
            self::USAGE => 'Usage',
            self::ADJUSTMENT => 'Adjustment',
            self::REFUND => 'Refund',
            self::EXPIRED => 'Expired',
            self::BONUS => 'Bonus',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PURCHASE => 'success',
            self::USAGE => 'danger',
            self::ADJUSTMENT => 'warning',
            self::REFUND => 'info',
            self::EXPIRED => 'gray',
            self::BONUS => 'primary',
        };
    }
}
