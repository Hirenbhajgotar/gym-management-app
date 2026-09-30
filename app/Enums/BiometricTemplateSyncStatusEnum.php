<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BiometricTemplateSyncStatusEnum: string implements HasColor, HasLabel
{
    case Synced = 'synced';
    case PendingDeletion = 'pending_deletion';
    case Deleted = 'deleted';
    case Superseded = 'superseded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Synced => 'Synced',
            self::PendingDeletion => 'Pending Hardware Deletion',
            self::Deleted => 'Deleted',
            self::Superseded => 'Superseded',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Synced => 'success',
            self::PendingDeletion => 'warning',
            self::Deleted => 'danger',
            self::Superseded => 'gray',
        };
    }
}
