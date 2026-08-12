<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\Log;

enum WhatsAppPhoneNumberStatusEnum: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case PURCHASED = 'purchased';
    case DEACTIVATED = 'deactivated';
    case UNVERIFIED = 'unverified';
    case SUSPENDED = 'suspended';
    case RESTRICTED = 'restricted';
    case DELETED = 'deleted';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACTIVE => 'Active',
            self::PURCHASED => 'Purchased',
            self::DEACTIVATED => 'Deactivated',
            self::UNVERIFIED => 'Unverified',
            self::SUSPENDED => 'Suspended',
            self::RESTRICTED => 'Restricted',
            self::DELETED => 'Deleted',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::ACTIVE => 'success',
            self::PURCHASED => 'warning',
            self::DEACTIVATED => 'danger',
            self::UNVERIFIED => 'danger',
            self::SUSPENDED => 'danger',
            self::RESTRICTED => 'danger',
            self::DELETED => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::ACTIVE => 'heroicon-o-check-circle',
            self::PURCHASED => 'heroicon-o-clock',
            self::DEACTIVATED => 'heroicon-o-x-circle',
            self::UNVERIFIED => 'heroicon-o-x-circle',
            self::SUSPENDED => 'heroicon-o-x-circle',
            self::RESTRICTED => 'heroicon-o-x-circle',
            self::DELETED => 'heroicon-o-x-circle',
        };
    }

    public static function fromMetaPhoneNumberStatus(?string $status): self
    {
        if ($status === null || $status === '' || strtoupper($status) === 'NONE') {
            return self::PENDING;
        }

        return match (strtoupper($status)) {
            'ACTIVE', 'PURCHASED', 'CONNECTED' => self::ACTIVE,
            'DEACTIVATED', 'DISCONNECTED', 'OFFLINE' => self::DEACTIVATED,
            'PENDING' => self::PENDING,
            'UNVERIFIED' => self::UNVERIFIED,
            'SUSPENDED' => self::SUSPENDED,
            'RESTRICTED', 'FLAGGED', 'RATE_LIMITED' => self::RESTRICTED,
            'DELETED' => self::DELETED,
            default => self::logAndFallback($status),
        };
    }

    private static function logAndFallback(?string $status): self
    {
        Log::warning('Unknown Meta phone number status encountered', [
            'status' => $status,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
        ]);

        return self::DEACTIVATED;
    }
}
