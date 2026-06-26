<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ExpenseStatus: string implements HasColor, HasIcon, HasLabel
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending Approval',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::PAID => 'Paid',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::PAID => 'info',
            self::CANCELLED => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DRAFT => 'phosphor-note-pencil',
            self::PENDING => 'phosphor-hourglass',
            self::APPROVED => 'phosphor-check-circle',
            self::REJECTED => 'phosphor-x-circle',
            self::PAID => 'phosphor-coins',
            self::CANCELLED => 'phosphor-prohibit',
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
     * Check if the status allows editing
     */
    public function canEdit(): bool
    {
        return in_array($this, [self::DRAFT, self::REJECTED]);
    }

    /**
     * Check if the status requires approval
     */
    public function requiresApproval(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Check if the expense is finalized (cannot be modified)
     */
    public function isFinalized(): bool
    {
        return in_array($this, [self::APPROVED, self::PAID, self::CANCELLED]);
    }
}
