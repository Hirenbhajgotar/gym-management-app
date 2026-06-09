<?php

namespace App\Filament\Admin\Widgets\Stats;

use App\Enums\PaymentStatus;
use App\Helpers\Number as HelpersNumber;
use App\Models\MemberPayment;
use App\Services\DashboardCacheService;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * Dashboard card that shows daily / weekly / monthly / yearly collections.
 */
class CollectionStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 1;

    protected function getListeners(): array
    {
        $gymId = Filament::getTenant()?->id;

        return [
            "echo-private:reports.{$gymId},.RevenueStatsUpdated" => '$refresh',
        ];
    }

    protected function getStats(): array
    {
        $gymId = Filament::getTenant()?->id;

        if (! $gymId) {
            return [];
        }

        return Cache::flexible(DashboardCacheService::COLLECTION_STATS . $gymId, DashboardCacheService::getCacheTtl(), function () {
            // Only successful payments are considered "collection".
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
        });
    }

    /**
     * Ensure we always return at least a root tag to prevent Livewire errors.
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        if (Filament::getTenant()?->id === null) {
            return view()->make('filament-widgets::blank');
        }

        return parent::render();
    }
}
