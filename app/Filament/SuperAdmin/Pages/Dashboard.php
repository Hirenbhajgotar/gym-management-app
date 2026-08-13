<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Pages;

use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /**
     * Get the header actions for the dashboard page.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('logViewer')
                ->label('View Logs')
                ->icon('heroicon-o-document-text')
                ->color('warning')
                ->url(url(config()->string('log-viewer.route_path', 'log-viewer')), shouldOpenInNewTab: true),

            Action::make('vantageQueue')
                ->label('View Queue')
                ->icon('heroicon-o-queue-list')
                ->color('warning')
                ->url(url(config()->string('vantage.route_prefix', 'vantage')), shouldOpenInNewTab: true),
        ];
    }
}
