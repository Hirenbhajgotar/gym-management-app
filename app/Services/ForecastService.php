<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\GymMember;
use App\Models\MemberPayment;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

class ForecastService
{
    /**
     * Forecast revenue for the next N months.
     * Uses a simple linear regression approach based on the last 6 months.
     */
    public function forecastRevenue(int $gymId, int $monthsToForecast = 3): array
    {
        // Get last 6 months of actual data
        $historicalData = Trend::query(
            MemberPayment::query()->where('gym_id', $gymId)->where('status', PaymentStatus::COMPLETED)
        )
            ->between(
                start: now()->subMonths(6),
                end: now()
            )
            ->perMonth()
            ->sum('amount');

        return $this->calculateForecast($historicalData, $monthsToForecast);
    }

    /**
     * Forecast member growth for the next N months.
     */
    public function forecastMemberGrowth(int $gymId, int $monthsToForecast = 3): array
    {
        // Get last 6 months of active member counts
        // Note: This is an approximation using created_at count for growth trend
        $historicalData = Trend::query(
            GymMember::query()->where('gym_id', $gymId)
        )
            ->between(
                start: now()->subMonths(6),
                end: now()
            )
            ->perMonth()
            ->count();

        return $this->calculateForecast($historicalData, $monthsToForecast);
    }

    /**
     * Calculate forecast using Linear Regression (Least Squares Method).
     */
    protected function calculateForecast(Collection $historicalData, int $monthsToForecast): array
    {
        $values = $historicalData->map(fn (TrendValue $value) => $value->aggregate)->values()->toArray();
        $n = count($values);

        if ($n < 2) {
            return []; // Not enough data to forecast
        }

        // x = time periods (0, 1, 2...)
        // y = values (revenue, members...)

        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumXX = 0;

        foreach ($values as $i => $y) {
            $sumX += $i;
            $sumY += $y;
            $sumXY += ($i * $y);
            $sumXX += ($i * $i);
        }

        // Calculate slope (m) and intercept (b)
        // m = (n*sumXY - sumX*sumY) / (n*sumXX - sumX^2)
        // b = (sumY - m*sumX) / n

        $divisor = ($n * $sumXX) - ($sumX * $sumX);

        if ($divisor == 0) {
            $slope = 0;
        } else {
            $slope = (($n * $sumXY) - ($sumX * $sumY)) / $divisor;
        }

        $intercept = ($sumY - ($slope * $sumX)) / $n;

        // Generate forecast
        $forecast = [];
        $lastDate = Date::parse($historicalData->last()->date);

        for ($i = 1; $i <= $monthsToForecast; $i++) {
            $nextIndex = $n - 1 + $i; // Continue 'x' sequence
            $predictedValue = ($slope * $nextIndex) + $intercept;

            // Ensure no negative values for revenue/members
            $predictedValue = max(0, $predictedValue);

            $forecast[] = [
                'date' => $lastDate->copy()->addMonths($i)->format('M Y'),
                'value' => round($predictedValue, 2),
                'type' => 'forecast',
            ];
        }

        return $forecast;
    }

    /**
     * Get combined historical and forecast data for charting.
     */
    public function getChartData(int $gymId, string $metric = 'revenue', int $monthsToForecast = 3): array
    {
        if ($metric === 'revenue') {
            $historical = Trend::query(
                MemberPayment::query()->where('gym_id', $gymId)->where('status', PaymentStatus::COMPLETED)
            )
                ->between(start: now()->subMonths(6), end: now())
                ->perMonth()
                ->sum('amount');

            $forecast = $this->forecastRevenue($gymId, $monthsToForecast);

        } else {
            $historical = Trend::query(
                GymMember::query()->where('gym_id', $gymId)
            )
                ->between(start: now()->subMonths(6), end: now())
                ->perMonth()
                ->count();

            $forecast = $this->forecastMemberGrowth($gymId, $monthsToForecast);
        }

        $formattedHistory = $historical->map(fn (TrendValue $val) => [
            'date' => Date::parse($val->date)->format('M Y'),
            'value' => $val->aggregate,
            'type' => 'actual',
        ])->values()->all();

        return array_merge($formattedHistory, $forecast);
    }
}
