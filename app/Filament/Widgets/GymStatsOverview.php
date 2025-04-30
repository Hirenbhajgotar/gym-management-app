<?php

namespace App\Filament\Widgets;

use App\Models\Gym;
use App\Models\GymMember;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GymStatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $pollingInterval = '60s'; // Refresh every 60 seconds

    public static function canView(): bool
    {
        return auth()->user()->hasRole('gym_owner');
    }

    protected function getStats(): array
    {
        $gymId = $this->filters['gymId'] ?? null;
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        $user = auth()->user();
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        $selectedGym = null;

        if ($gymId) {
            $selectedGym = Gym::find($gymId);
        }

        $description = $selectedGym ? 'Members in your '.$selectedGym?->name.' gym' : 'Members in all your gyms';

        // Total Gyms (filtered for gym_owner)
        $totalGyms = Gym::where('owner_id', $user->id)->count();

        // Total Members in All Gyms (filtered for gym_owner)
        $totalMembers = GymMember::whereHas('gym', fn ($query) => $query->where('owner_id', $user->id))
            ->when($gymId, fn ($query) => $query->where('gym_id', $gymId))
            // ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
            //     $query->where('created_at', '>=', $startDate)
            //         ->where('created_at', '<=', $endDate);
            // })
            ->count();

        // Members Joined in Current Month (filtered for gym_owner)
        $membersJoined = GymMember::whereHas('gym', fn ($query) => $query->where('owner_id', $user->id))
            ->when($gymId, fn ($query) => $query->where('gym_id', $gymId))
            // ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
            //     $query->where('created_at', '>=', $startDate)
            //         ->where('created_at', '<=', $endDate);
            // })
            ->when(! ($startDate && $endDate), function ($query) use ($currentMonth, $currentYear) {
                $query->whereMonth('created_at', $currentMonth)
                    ->whereYear('created_at', $currentYear);
            })
            ->count();

        $gymChartData = [];
        $gymChartLabels = [];
        $monthsBack = 5; // Last 5 months
        for ($i = $monthsBack - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $count = Gym::where('owner_id', $user->id)
                ->when($gymId, fn ($query) => $query->where('id', $gymId))
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count();
            $gymChartData[] = $count;
            $gymChartLabels[] = $date->format('M'); // e.g., Jan, Feb, etc.
        }

        // Dynamic Chart Data for Total Members (last 5 months)
        $memberChartData = [];
        $memberChartLabels = [];
        for ($i = $monthsBack - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $count = GymMember::whereHas('gym', fn ($query) => $query->where('owner_id', $user->id))
                ->when($gymId, fn ($query) => $query->where('id', $gymId))
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count();
            $memberChartData[] = $count;
            $memberChartLabels[] = $date->format('M'); // e.g., Jan, Feb, etc.
        }

        return [
            Stat::make('Total Gyms', $totalGyms)
                ->description('Gyms you manage')
                ->color('success')
                ->chart($gymChartData) // Example chart data
                ->extraAttributes(['class' => 'cursor-pointer']),
            Stat::make('Total Members', $totalMembers)
                ->description($description)
                ->color('primary')
                ->chart($memberChartData), // Example chart data
            Stat::make('Members Joined', $membersJoined)
                ->description('This month')
                ->color('info')
                ->chart([$membersJoined]),
        ];
    }
}
