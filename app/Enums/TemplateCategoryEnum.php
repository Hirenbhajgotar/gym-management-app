<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TemplateCategoryEnum: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case UTILITY = 'utility';
    case MARKETING = 'marketing';
    case AUTHENTICATION = 'authentication';

    public function getLabel(): string
    {
        return match ($this) {
            self::UTILITY => 'Utility',
            self::MARKETING => 'Marketing',
            self::AUTHENTICATION => 'Authentication',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::UTILITY => 'success',
            self::MARKETING => 'warning',
            self::AUTHENTICATION => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::UTILITY => 'heroicon-o-wrench-screwdriver',
            self::MARKETING => 'heroicon-o-megaphone',
            self::AUTHENTICATION => 'heroicon-o-key',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::UTILITY => '🔧 Utility — Transactional messages (reminders, updates, confirmations)',
            self::MARKETING => '📢 Marketing — Promotional messages (offers, campaigns, announcements)',
            self::AUTHENTICATION => '🔑 Authentication — Authentication messages (passwords, OTPs, security)',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function toSelectArray(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->getDescription()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function toFilterArray(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])
            ->all();
    }

    /**
     * Attempt to create from a value, falling back to a default if invalid.
     */
    public static function tryFromOrDefault(?string $value, self $default = self::UTILITY): self
    {
        if ($value === null) {
            return $default;
        }

        $lower = strtolower($value);

        return self::tryFrom($lower) ?? $default;
    }
}
