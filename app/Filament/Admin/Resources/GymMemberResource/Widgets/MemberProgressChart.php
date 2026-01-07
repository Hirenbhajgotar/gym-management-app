<?php

namespace App\Filament\Admin\Resources\GymMemberResource\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MemberProgressChart extends ChartWidget
{
    protected static ?string $heading = 'Weight Progress';

    protected int|string|array $columnSpan = 'full';

    // Filament passes the page record to widgets as $record
    public ?Model $record = null;

    protected static ?string $pollingInterval = null;

    protected function getData(): array
    {
        $record = $this->record;

        if (! $record) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // Cache key based on member ID and latest measurement update
        // We use flexible cache: fresh for 1 hour, stale allowed for 1 week
        return Cache::flexible("member_weight_progress_{$record->id}", [3600, 604800], function () use ($record) {
            // Get the earliest measurement date to start the trend
            $firstMeasurement = $record->measurements()->oldest('measured_at')->first();

            if (! $firstMeasurement) {
                return [
                    'datasets' => [],
                    'labels' => [],
                ];
            }

            $measurements = $record->measurements()
                ->orderBy('measured_at')
                ->get();

            return [
                'datasets' => [
                    [
                        'label' => 'Weight (kg)',
                        'data' => $measurements->pluck('weight'),
                        'borderColor' => '#3b82f6',
                        'fill' => false,
                        'tension' => 0.4,
                        'spanGaps' => true, // Connect lines across missing days
                    ],
                ],
                'labels' => $measurements->pluck('measured_at')->map(fn ($date) => $date->format('M d')),
            ];
        }
        );
    }

    protected function getType(): string
    {
        return 'line';
    }
}
