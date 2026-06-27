<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters;

use App\Enums\DefaultRole;
use Filament\Clusters\Cluster;

class Attendances extends Cluster
{
    protected static ?string $navigationGroup = 'Gym Management';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole([
            DefaultRole::SUPER_ADMIN->value,
            DefaultRole::ADMIN->value,
            DefaultRole::GYM_OWNER->value,
        ])) {
            return true;
        }

        return $user->can('view_any_attendance')
            || $user->can('view_attendance');
    }
}
