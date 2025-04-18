<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Widget;

class GymStats extends Widget
{
    protected static string $view = 'filament.widgets.gym-stats';

    protected function getStats(): array
    {
        $user = auth()->user();
        $gyms = $user->hasRole('gym_owner') ? $user->gyms() : \App\Models\Gym::query();
        $members = \App\Models\GymMember::whereIn('gym_id', $gyms->pluck('id'));

        return [
            Stat::make('Total Gyms', $gyms->count())
                ->description('Number of gyms managed')
                ->color('primary'),
            Stat::make('Total Members', $members->count())
                ->description('Active gym members')
                ->color('success'),
        ];
    }
}
