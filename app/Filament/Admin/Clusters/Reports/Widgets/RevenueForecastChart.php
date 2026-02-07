<?php

namespace App\Filament\Admin\Clusters\Reports\Widgets;

use App\Services\ForecastService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class RevenueForecastChart extends ChartWidget
{
    protected static ?string $heading = 'Revenue Forecast (Next 3 Months)';

    protected static ?string $description = 'Historical revenue vs. projected trend';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    /**
     * Get Livewire listeners for real-time updates via Echo.
     */
    protected function getListeners(): array
    {
        $gymId = Filament::getTenant()?->id;

        return [
            "echo-private:reports.{$gymId},.RevenueStatsUpdated" => '$refresh',
        ];
    }

    protected function getData(): array
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        if (! $gym) {
            return [];
        }

        $service = app(ForecastService::class);
        $chartData = $service->getChartData($gym->id, 'revenue', 3);

        $labels = array_column($chartData, 'date');
        $values = array_column($chartData, 'value');
        $types = array_column($chartData, 'type');

        // Create two datasets: Actual and Forecast
        // We pad the arrays so they line up correctly on the chart
        $actualData = [];
        $forecastData = [];
        $backgroundColors = [];
        $borderColors = [];

        foreach ($chartData as $index => $point) {
            if ($point['type'] === 'actual') {
                $actualData[] = $point['value'];
                $forecastData[] = null; // No forecast data for actual months
            } else {
                // For the first forecast point, we should ideally link it to the last actual point
                // But specifically for bar/line charts, we'll just put it in the forecast array
                $actualData[] = null;
                $forecastData[] = $point['value'];
            }
        }

        // To make the line continuous, the last 'actual' point needs to be the start of 'forecast'
        // But for simplicity in a bar/line combo or just differentiation, we'll use color coding or separate datasets

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Actual Revenue',
                    'data' => $actualData,
                    'backgroundColor' => '#3b82f6', // primary-500
                    'borderColor' => '#3b82f6',
                    'fill' => true,
                ],
                [
                    'label' => 'Projected Revenue',
                    'data' => $forecastData,
                    'backgroundColor' => '#93c5fd', // primary-300
                    'borderColor' => '#93c5fd',
                    'borderDash' => [5, 5], // Dashed line for forecast
                    'fill' => false,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
