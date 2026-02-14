<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WhatsAppCreditTransactionEnum: string implements HasLabel
{
    case PURCHASE = 'purchase';
    case USAGE = 'usage';
    case ADJUSTMENT = 'adjustment';
    case REFUND = 'refund';
    case EXPIRED = 'expired';
    case BONUS = 'bonus';

    public function getLabel(): ?string
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
}
