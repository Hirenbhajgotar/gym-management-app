<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Location extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?int $navigationSort = 100;
}
