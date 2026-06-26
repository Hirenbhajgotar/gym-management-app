<?php

namespace App\Filament\Admin\Clusters;

use App\Enums\DefaultRole;
use Filament\Clusters\Cluster;

class Permissions extends Cluster
{
    protected static ?string $navigationGroup = 'Gym Management';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->hasRole(DefaultRole::GYM_OWNER->value) ?? false;
    }
}
