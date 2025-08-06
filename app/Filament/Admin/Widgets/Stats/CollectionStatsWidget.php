<?php

namespace App\Filament\Admin\Widgets\Stats;

use App\Enums\PaymentStatus;
use App\Helpers\Number as HelpersNumber;
use App\Models\MemberPayment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * Dashboard card that shows daily / weekly / monthly / yearly collections.
 */
class CollectionStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '60s'; // auto-refresh every minute

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Only successful payments are considered “collection”.
        $query = MemberPayment::query()
            ->where('status', PaymentStatus::COMPLETED);

        $today = $query->clone()->whereDate('payment_date', today())->sum('amount');

        $thisWeek = $query->clone()
            ->whereBetween('payment_date', [now()->startOfWeek(), now()->endOfWeek()])
            ->sum('amount');

        $thisMonth = $query->clone()
            ->whereYear('payment_date', now()->year)
            ->whereMonth('payment_date', now()->month)
            ->sum('amount');

        $thisYear = $query->clone()
            ->whereYear('payment_date', now()->year)
            ->sum('amount');

        // --- Formatting helper -------------------------------------------------
        $format = function ($value) {
            return HelpersNumber::abbreviateCurrency($value);
        };

        return [
            Stat::make('Today\'s Collection', $format($today))
                ->icon('heroicon-o-banknotes')
                ->description('Collection received today')
                ->color('success'),

            Stat::make('This Week\'s Collection', $format($thisWeek))
                ->icon('heroicon-o-banknotes')
                ->description('Collection received this week')
                ->color('primary'),

            Stat::make('This Month\'s Collection', $format($thisMonth))
                ->icon('heroicon-o-banknotes')
                ->description('Collection received this month')
                ->color('warning'),

            Stat::make('This Year\'s Collection', $format($thisYear))
                ->icon('heroicon-o-banknotes')
                ->description('Collection received this year')
                ->color('info'),
        ];
    }
}
