<?php

namespace App\Filament\Admin\Resources\GymMemberResource\Widgets;

use App\Filament\BaseWidgets\BaseChartWidget;
use App\Helpers\BmiHelper;
use App\Models\GymMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;

class MemberProgressChart extends BaseChartWidget
{
    protected static ?string $heading = 'Health Score Progress';

    protected int|string|array $columnSpan = 'full';

    // Filament passes the page record to widgets as $record
    public ?Model $record = null;

    protected static ?string $pollingInterval = null;

    #[On('member-measurement-updated')]
    public function refreshChart(): void
    {
        if ($this->record) {
            Cache::forget(GymMember::MEMBER_WEIGHT_PROGRESS_CACHE_KEY . $this->record->id);
        }

        $this->updateChartData();
    }

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
        return Cache::flexible(GymMember::MEMBER_WEIGHT_PROGRESS_CACHE_KEY . $record->id, [now()->addHour(), now()->addWeek()], function () use ($record) {
            $measurements = $record->measurements()
                ->orderBy('measured_at')
                ->get();

            if ($measurements->isEmpty()) {
                return [
                    'datasets' => [],
                    'labels' => [],
                ];
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Health Score',
                        'data' => $measurements->map(fn ($m) => BmiHelper::calculateHealthScore($m->bmi))->toArray(),
                        'borderColor' => '#10b981',
                        'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                        'tension' => 0.4,
                        'spanGaps' => true,
                        'fill' => true,
                    ],
                    [
                        'label' => 'BMI',
                        'data' => $measurements->pluck('bmi')->toArray(),
                        'borderColor' => '#64748b',
                        'backgroundColor' => 'transparent',
                        'borderDash' => [5, 5],
                        'yAxisID' => 'y1', // Secondary axis
                    ],
                ],
                'labels' => $measurements->pluck('measured_at')->map(fn ($date) => $date->format('M d, Y')),
            ];
        });
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'plugins' => [
                'legend' => ['display' => true],
                'tooltip' => [
                    'callbacks' => [
                        'label' => "function(context) {
                            if (context.dataset.label === 'Health Score') {
                                return 'Health: ' + context.parsed.y + '/100';
                            }
                            return 'BMI: ' + context.parsed.y;
                        }",
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'min' => 0,
                    'max' => 100,
                    'grid' => ['color' => 'rgba(16, 185, 129, 0.1)'],
                    'title' => ['display' => true, 'text' => 'Health Score'],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'min' => 15,
                    'max' => 40,
                    'grid' => ['drawOnChartArea' => false],
                    'title' => ['display' => true, 'text' => 'BMI'],
                ],
            ],
        ];
    }
}
