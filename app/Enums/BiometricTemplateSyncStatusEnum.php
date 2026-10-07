<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BiometricTemplateSyncStatusEnum: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Propagated = 'propagated';
    case Synced = 'synced';
    case PendingDeletion = 'pending_deletion';
    case Deleted = 'deleted';
    case Superseded = 'superseded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Propagated => 'Propagated',
            self::Synced => 'Synced',
            self::PendingDeletion => 'Pending Hardware Deletion',
            self::Deleted => 'Deleted',
            self::Superseded => 'Superseded',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Propagated => 'warning',
            self::Synced => 'success',
            self::PendingDeletion => 'danger',
            self::Deleted => 'danger',
            self::Superseded => 'gray',
        };
    }
}
