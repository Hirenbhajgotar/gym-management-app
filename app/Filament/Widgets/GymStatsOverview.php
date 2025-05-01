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
        $user = auth()->user();
        $gymId = $this->filters['gymId'] ?? null;
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        $selectedGym = $gymId ? Gym::find($gymId) : null;
        $description = $selectedGym ? 'Members in your '.$selectedGym->name.' gym' : 'Members in all your gyms';

        // Total Members (filtered for gym_owner)
        $totalMembers = GymMember::whereHas('gym', fn ($query) => $query->where('owner_id', $user->id))
            ->when($gymId, fn ($query) => $query->where('gym_id', $gymId))
            ->when($startDate && $endDate, fn ($query) => $query->whereBetween('created_at', [$startDate, $endDate]))
            ->count();

        // Determine the date range for comparison
        $currentStart = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfMonth();
        $currentEnd = $endDate ? Carbon::parse($endDate) : Carbon::now()->endOfMonth();
        $rangeDays = $currentStart->diffInDays($currentEnd) + 1; // Include end date

        // Define previous period (same duration, just before the current range)
        $previousEnd = $currentStart->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($rangeDays - 1);

        // Members Joined in Current Period
        $membersJoinedCurrent = GymMember::whereHas('gym', fn ($query) => $query->where('owner_id', $user->id))
            ->when($gymId, fn ($query) => $query->where('gym_id', $gymId))
            ->whereBetween('created_at', [$currentStart, $currentEnd])
            // ->ddRawSql()
            ->count();

        // Members Joined in Previous Period
        $membersJoinedPrevious = GymMember::whereHas('gym', fn ($query) => $query->where('owner_id', $user->id))
            ->when($gymId, fn ($query) => $query->where('gym_id', $gymId))
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();

        // Compare and set styling
        $changeDirection = '';
        $color = 'primary';
        $descriptionIcon = 'heroicon-m-arrow-trending-up';

        if ($membersJoinedCurrent > $membersJoinedPrevious) {
            $changeDirection = 'increased by '.($membersJoinedCurrent - $membersJoinedPrevious);
            $color = 'success';
            $descriptionIcon = 'heroicon-m-arrow-trending-up';
        } elseif ($membersJoinedCurrent < $membersJoinedPrevious) {
            $changeDirection = 'decreased by '.($membersJoinedPrevious - $membersJoinedCurrent);
            $color = 'danger';
            $descriptionIcon = 'heroicon-m-arrow-trending-down';
        } else {
            $changeDirection = 'no change';
            $color = 'primary';
            $descriptionIcon = 'heroicon-m-minus';
        }

        // Dynamic Chart Data for Total Members (last 5 months)
        $memberChartData = [];
        $memberChartLabels = [];
        $monthsBack = 5;
        for ($i = $monthsBack - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $count = GymMember::whereHas('gym', fn ($query) => $query->where('owner_id', $user->id))
                ->when($gymId, fn ($query) => $query->where('gym_id', $gymId))
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count();
            $memberChartData[] = $count;
            $memberChartLabels[] = $date->format('M');
        }

        return [
            Stat::make('Total Members', $totalMembers)
                ->description($description)
                ->color('primary')
                ->chart($memberChartData),

            Stat::make('Members Joined', $membersJoinedCurrent)
                ->description("{$changeDirection} from previous period")
                ->descriptionIcon($descriptionIcon)
                ->color($color)
                ->chart([$membersJoinedCurrent, $membersJoinedPrevious]),
        ];
    }
}
