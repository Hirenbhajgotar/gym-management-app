<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MemberPayment;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class GymStatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $pollingInterval = '900s'; // Refresh every 60 seconds

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()->hasRole('gym_owner');
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $gymId = Filament::getTenant()->id ?? null;
        $monthYear = $this->filters['monthYear'] ?: Carbon::now()->format('Y-m');

        $selectedGym = $gymId ? Gym::find($gymId) : null;
        $descriptionBase = $selectedGym ? 'Members in your ' . $selectedGym->name . ' gym' : 'Members in all your gyms';

        // Parse month-year to determine date range
        $currentStart = Carbon::createFromFormat('Y-m', $monthYear)->startOfMonth();
        $currentEnd = $currentStart->copy()->endOfMonth();
        $rangeDays = $currentStart->diffInDays($currentEnd) + 1;

        $previousEnd = $currentStart->copy()->subDay();
        $previousStart = $previousEnd->copy()->startOfMonth();

        // Total Members (filtered for gym_owner)
        // $totalMembers = GymMember::whereHas('gym', fn($query) => $query->where('owner_id', $user->id))
        //     ->when($gymId, fn($query) => $query->where('gym_id', $gymId))
        //     ->count();

        $totalMembers = GymMember::where('gym_id', $gymId)->count(); // Placeholder for total members, replace with actual query

        // Members Joined in Current Period
        // $membersJoinedCurrent = GymMember::whereHas('gym', fn($query) => $query->where('owner_id', $user->id))
        //     ->when($gymId, fn($query) => $query->where('gym_id', $gymId))
        //     ->whereBetween('created_at', [$currentStart, $currentEnd])
        //     ->count();

        $membersJoinedCurrent = GymMember::where('gym_id', $gymId)
            ->whereBetween('created_at', [$currentStart, $currentEnd])
            ->count(); // Placeholder for members joined in current period, replace with actual query

        // Members Joined in Previous Period
        // $membersJoinedPrevious = GymMember::whereHas('gym', fn($query) => $query->where('owner_id', $user->id))
        //     ->when($gymId, fn($query) => $query->where('gym_id', $gymId))
        //     ->whereBetween('created_at', [$previousStart, $previousEnd])
        //     ->count();

        $membersJoinedPrevious = GymMember::where('gym_id', $gymId)
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count(); // Placeholder for members joined in previous period, replace with actual query

        // Dynamic styling for Members Joined
        $changeDirection = '';
        $color = 'primary';
        $descriptionIcon = 'heroicon-m-arrow-trending-up';

        if ($membersJoinedCurrent > $membersJoinedPrevious) {
            $changeDirection = 'increased by ' . ($membersJoinedCurrent - $membersJoinedPrevious);
            $color = 'success';
            $descriptionIcon = 'heroicon-m-arrow-trending-up';
        } elseif ($membersJoinedCurrent < $membersJoinedPrevious) {
            $changeDirection = 'decreased by ' . ($membersJoinedPrevious - $membersJoinedCurrent);
            $color = 'danger';
            $descriptionIcon = 'heroicon-m-arrow-trending-down';
        } else {
            $changeDirection = 'no change';
            $descriptionIcon = 'heroicon-m-minus';
        }

        // Revenue Calculation
        // $currentRevenue = MemberPayment::whereHas('member.gym', fn($query) => $query->where('owner_id', $user->id))
        //     ->when($gymId, fn($query) => $query->whereHas('member', fn($q) => $q->where('gym_id', $gymId)))
        //     ->whereBetween('payment_date', [$currentStart, $currentEnd])
        //     ->sum('amount');

        $currentRevenue = MemberPayment::when($gymId, fn($query) => $query->whereHas('member', fn($q) => $q->where('gym_id', $gymId)))
            ->whereBetween('payment_date', [$currentStart, $currentEnd])
            ->sum('amount'); // Placeholder for current revenue, replace with actual query

        // $previousRevenue = MemberPayment::whereHas('member.gym', fn($query) => $query->where('owner_id', $user->id))
        //     ->when($gymId, fn($query) => $query->whereHas('member', fn($q) => $q->where('gym_id', $gymId)))
        //     ->whereBetween('payment_date', [$previousStart, $previousEnd])
        //     ->sum('amount');

        $previousRevenue = MemberPayment::when($gymId, fn($query) => $query->whereHas('member', fn($q) => $q->where('gym_id', $gymId)))
            ->whereBetween('payment_date', [$previousStart, $previousEnd])
            ->sum('amount'); // Placeholder for previous revenue, replace with actual query

        // Dynamic styling for Revenue
        $revenueDescription = $selectedGym ? 'Revenue from ' . $selectedGym->name : 'Total revenue from all gyms';
        $revenueChange = '';
        $revenueColor = 'info';
        $revenueIcon = 'heroicon-m-currency-dollar';

        $percentageChange = $previousRevenue > 0 ? (($currentRevenue - $previousRevenue) / $previousRevenue) * 100 : ($currentRevenue > 0 ? 100 : 0);

        if ($currentRevenue > $previousRevenue) {
            $revenueChange = 'up ' . number_format($percentageChange, 1) . '% from previous period';
            $revenueColor = 'success';
            $revenueIcon = 'heroicon-m-arrow-trending-up';
        } elseif ($currentRevenue < $previousRevenue) {
            $revenueChange = 'down ' . number_format(abs($percentageChange), 1) . '% from previous period';
            $revenueColor = 'danger';
            $revenueIcon = 'heroicon-m-arrow-trending-down';
        } else {
            $revenueChange = 'no change from previous period';
            $revenueIcon = 'heroicon-m-minus';
        }

        // Dynamic Chart Data for Revenue (last 3 months)
        $revenueChartData = [];
        $revenueChartLabels = [];
        $periodsBack = 3;
        for ($i = $periodsBack - 1; $i >= 0; $i--) {
            $periodStart = $currentStart->copy()->subMonths($i)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();
            // $revenue = MemberPayment::whereHas('member.gym', fn($query) => $query->where('owner_id', $user->id))
            //     ->when($gymId, fn($query) => $query->whereHas('member', fn($q) => $q->where('gym_id', $gymId)))
            //     ->whereBetween('payment_date', [$periodStart, $periodEnd])
            //     ->sum('amount');

            $revenue = MemberPayment::when($gymId, fn($query) => $query->whereHas('member', fn($q) => $q->where('gym_id', $gymId)))
                ->whereBetween('payment_date', [$periodStart, $periodEnd])
                ->sum('amount');

            $revenueChartData[] = $revenue;
            $revenueChartLabels[] = $periodStart->format('M Y');
        }

        // Dynamic Chart Data for Total Members (last 3 months)
        $memberChartData = [];
        $memberChartLabels = [];
        for ($i = $periodsBack - 1; $i >= 0; $i--) {
            $periodStart = $currentStart->copy()->subMonths($i)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();
            // $count = GymMember::whereHas('gym', fn($query) => $query->where('owner_id', $user->id))
            //     ->when($gymId, fn($query) => $query->where('gym_id', $gymId))
            //     ->whereBetween('created_at', [$periodStart, $periodEnd])
            //     ->count();

            $count = GymMember::when($gymId, fn($query) => $query->where('gym_id', $gymId))
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->count();

            $memberChartData[] = $count;
            $memberChartLabels[] = $periodStart->format('M Y');
        }

        return [
            Stat::make('Total Members', $totalMembers)
                ->icon('heroicon-o-users')
                ->description($descriptionBase)
                ->color('primary')
                ->chart($memberChartData),
            Stat::make('Members Joined', $membersJoinedCurrent)
                ->icon('heroicon-o-user-plus')
                ->description("{$changeDirection} from previous period")
                ->descriptionIcon($descriptionIcon)
                ->color($color)
                ->chart($memberChartData),
            Stat::make('Revenue', Number::currency($currentRevenue, in: 'INR'))
                ->icon('heroicon-o-banknotes')
                ->description("{$revenueDescription} ({$revenueChange})")
                ->descriptionIcon($revenueIcon)
                ->color($revenueColor)
                ->chart($revenueChartData)
                ->extraAttributes(['class' => 'font-bold']),
        ];
    }
}
