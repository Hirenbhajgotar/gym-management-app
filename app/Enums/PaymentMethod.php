<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case UPI = 'upi';
    case CREDIT_DEBIT_CARD = 'credit_debit_card';

    public function getLabel(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::UPI => 'UPI',
            self::CREDIT_DEBIT_CARD => 'Credit/Debit Card',
        };
    }
}
