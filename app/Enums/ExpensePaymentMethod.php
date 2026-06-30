<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ExpensePaymentMethod: string implements HasIcon, HasLabel
{
    case CASH = 'cash';
    case UPI = 'upi';
    case BANK_TRANSFER = 'bank_transfer';
    case CREDIT_DEBIT_CARD = 'credit_debit_card';
    case CHEQUE = 'cheque';
    case PETTY_CASH = 'petty_cash';
    case ONLINE = 'online';

    public function getLabel(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::UPI => 'UPI',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::CREDIT_DEBIT_CARD => 'Credit/Debit Card',
            self::CHEQUE => 'Cheque',
            self::PETTY_CASH => 'Petty Cash',
            self::ONLINE => 'Online Payment',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::CASH => 'phosphor-money',
            self::UPI => 'phosphor-qr-code',
            self::BANK_TRANSFER => 'phosphor-bank',
            self::CREDIT_DEBIT_CARD => 'phosphor-credit-card',
            self::CHEQUE => 'phosphor-check-square',
            self::PETTY_CASH => 'phosphor-wallet',
            self::ONLINE => 'phosphor-globe',
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
     * Check if payment method requires reference number
     */
    public function requiresReference(): bool
    {
        return in_array($this, [
            self::UPI,
            self::BANK_TRANSFER,
            self::CHEQUE,
            self::ONLINE,
        ]);
    }
}
