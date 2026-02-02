<?php

namespace App\Filament\Admin\Clusters;

use Filament\Clusters\Cluster;

class Reports extends Cluster
{
    protected static ?string $navigationIcon = 'phosphor-chart-pie';

    protected static ?string $navigationLabel = 'Reports & Analytics';

    protected static ?int $navigationSort = 90;

    public static function getNavigationGroup(): ?string
    {
        return null; // Top-level navigation (no group)
    }
}
