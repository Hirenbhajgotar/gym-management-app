<?php

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Filament\BaseWidgets\BaseWidget;
use App\Models\Attendances;
use Filament\Facades\Filament;
use Illuminate\Database\Query\Builder;

class RealtimeAttendanceWidget extends BaseWidget
{
    protected static string $view = 'filament.clusters.reports.widgets.realtime-attendance-widget';

    protected static ?string $pollingInterval = null;

    public int $currentlyIn = 0;

    public int $todayTotal = 0;

    public int $peakToday = 0;

    public function mount(): void
    {
        $this->loadAttendanceData();
    }

    public function refreshAttendance(): void
    {
        $this->loadAttendanceData();
    }

    protected function loadAttendanceData(): void
    {
        $gymId = Filament::getTenant()->id;

        // Current check-ins (not checked out)
        $this->currentlyIn = Attendances::query()->where('gym_id', $gymId)
            ->whereNull('check_out_time')
            ->whereDate('check_in_time', today())
            ->count();

        // Total today
        $this->todayTotal = Attendances::query()->where('gym_id', $gymId)
            ->whereDate('check_in_time', today())
            ->count();

        // Peak today (highest concurrent check-ins in any hour)
        $this->peakToday = $this->calculatePeakToday($gymId);
    }

    protected function calculatePeakToday(int $gymId): int
    {
        $hours = [];

        // Group by hour and count
        for ($hour = 0; $hour < 24; $hour++) {
            $count = Attendances::query()->where('gym_id', $gymId)
                ->whereDate('check_in_time', today())
                ->whereTime('check_in_time', '>=', sprintf('%02d:00:00', $hour))
                ->whereTime('check_in_time', '<', sprintf('%02d:00:00', $hour + 1))
                ->where(function (Builder $query) use ($hour) {
                    $query->whereNull('check_out_time')
                        ->orWhereTime('check_out_time', '>', sprintf('%02d:00:00', $hour));
                })
                ->count();

            $hours[] = $count;
        }

        return max($hours) ?: 0;
    }

    public function getChannelName(): string
    {
        return 'attendance.' . Filament::getTenant()->id;
    }
}
