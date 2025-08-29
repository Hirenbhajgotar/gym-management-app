<?php

namespace App\Filament\SuperAdmin\Pages;

use Dotswan\FilamentLaravelPulse\Widgets\PulseCache;
use Dotswan\FilamentLaravelPulse\Widgets\PulseExceptions;
use Dotswan\FilamentLaravelPulse\Widgets\PulseQueues;
use Dotswan\FilamentLaravelPulse\Widgets\PulseServers;
use Dotswan\FilamentLaravelPulse\Widgets\PulseSlowOutGoingRequests;
use Dotswan\FilamentLaravelPulse\Widgets\PulseSlowQueries;
use Dotswan\FilamentLaravelPulse\Widgets\PulseSlowRequests;
use Dotswan\FilamentLaravelPulse\Widgets\PulseUsage;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Pages\Page;
use Filament\Support\Enums\ActionSize;

class PulseDashboard extends Page
{
    use HasFiltersAction;

    protected static ?string $navigationLabel = 'Pulse Dashboard';

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?string $title = 'System Monitoring Dashboard';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.super-admin.pages.pulse-dashboard';

    public function getColumns(): int|string|array
    {
        return 12;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('1h')
                    ->label('1 Hour')
                    ->action(fn () => $this->redirect($this->getUrl())),
                Action::make('24h')
                    ->label('24 Hours')
                    ->action(fn () => $this->redirect($this->getUrl(['period' => '24_hours']))),
                Action::make('7d')
                    ->label('7 Days')
                    ->action(fn () => $this->redirect($this->getUrl(['period' => '7_days']))),
            ])
                ->label('Time Period')
                ->icon('heroicon-m-funnel')
                ->size(ActionSize::Small)
                ->color('gray')
                ->button(),

            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-m-arrow-path')
                ->size(ActionSize::Small)
                ->color('gray')
                ->action(fn () => $this->redirect($this->getUrl(request()->query()))),
        ];
    }

    public function getWidgets(): array
    {
        return [
            PulseServers::class,
            PulseCache::class,
            PulseExceptions::class,
            PulseUsage::class,
            PulseQueues::class,
            PulseSlowQueries::class,
            PulseSlowRequests::class,
            PulseSlowOutGoingRequests::class,
        ];
    }
}
