<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\Log;

enum WhatsAppAccountStatusEnum: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case BLOCKED = 'blocked';
    case RESTRICTED = 'restricted';
    case REJECTED = 'rejected';
    case BANNED = 'banned';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::BLOCKED => 'Blocked',
            self::RESTRICTED => 'Restricted',
            self::REJECTED => 'Rejected',
            self::BANNED => 'Banned',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::ACTIVE => 'success',
            self::SUSPENDED => 'warning',
            self::BLOCKED => 'danger',
            self::RESTRICTED => 'danger',
            self::REJECTED => 'danger',
            self::BANNED => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::ACTIVE => 'heroicon-o-check-circle',
            self::SUSPENDED => 'heroicon-o-pause-circle',
            self::BLOCKED => 'heroicon-o-x-circle',
            self::RESTRICTED => 'heroicon-o-no-symbol',
            self::REJECTED => 'heroicon-o-no-symbol',
            self::BANNED => 'heroicon-o-shield-exclamation',
        };
    }

    /**
     * Check if status indicates the account is usable for messaging.
     */
    public function isUsable(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Check if status indicates the account is restricted or banned (Meta WABA state).
     */
    public function isWabaRestricted(): bool
    {
        return $this === self::RESTRICTED || $this === self::BANNED;
    }

    /**
     * Check if status indicates the account is suspended (Meta WABA state).
     */
    public function isWabaSuspended(): bool
    {
        return $this === self::SUSPENDED;
    }

    /**
     * Check if status indicates the account is blocked (Meta WABA state).
     */
    public function isWabaBlocked(): bool
    {
        return $this === self::BLOCKED;
    }

    /**
     * Map Meta WABA review status to WhatsAppAccountStatusEnum.
     */
    public static function fromMetaReviewStatus(?string $status): self
    {
        return match ($status) {
            'APPROVED' => self::ACTIVE,
            'REJECTED' => self::REJECTED,
            'PENDING' => self::PENDING,
            'DISABLED' => self::RESTRICTED,
            default => self::logAndFallback($status),
        };
    }

    private static function logAndFallback(?string $status): self
    {
        Log::warning('Unknown Meta WABA review status encountered', [
            'status' => $status,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
        ]);

        return self::PENDING;
    }
}
