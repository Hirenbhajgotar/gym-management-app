<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Attendances;
use App\Models\GymMember;
use App\Models\Lead;
use App\Models\MemberPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class GymHealthScoreService
{
    /**
     * Calculate overall gym health score (0-100).
     */
    public function calculateHealthScore(int $gymId): array
    {
        $cacheKey = ReportCacheService::GYM_HEALTH_SCORE . $gymId;

        return Cache::flexible($cacheKey, ReportCacheService::getCacheTtl(), function () use ($gymId) {
            $retentionScore = $this->calculateRetentionScore($gymId);
            $revenueScore = $this->calculateRevenueGrowthScore($gymId);
            $conversionScore = $this->calculateConversionScore($gymId);
            $attendanceScore = $this->calculateAttendanceScore($gymId);
            $collectionScore = $this->calculateCollectionScore($gymId);

            // Weighted average
            $overallScore = (
                ($retentionScore * 0.25) +
                ($revenueScore * 0.25) +
                ($conversionScore * 0.20) +
                ($attendanceScore * 0.15) +
                ($collectionScore * 0.15)
            );

            return [
                'overall_score' => round($overallScore, 1),
                'breakdown' => [
                    'retention' => round($retentionScore, 1),
                    'revenue_growth' => round($revenueScore, 1),
                    'lead_conversion' => round($conversionScore, 1),
                    'attendance' => round($attendanceScore, 1),
                    'payment_collection' => round($collectionScore, 1),
                ],
                'grade' => $this->getGrade($overallScore),
                'status' => $this->getStatus($overallScore),
            ];
        });
    }

    /**
     * Member Retention Rate (25% weight).
     */
    protected function calculateRetentionScore(int $gymId): float
    {
        $threeMonthsAgo = now()->subMonths(3);

        // Members who joined 3 months ago
        $cohort = GymMember::query()->where('gym_id', $gymId)
            ->whereBetween('created_at', [
                $threeMonthsAgo->copy()->startOfMonth(),
                $threeMonthsAgo->copy()->endOfMonth(),
            ])
            ->count();

        if ($cohort === 0) {
            return 0; // Neutral score if no data
        }

        // How many are still active
        $stillActive = GymMember::query()->where('gym_id', $gymId)
            ->whereBetween('created_at', [
                $threeMonthsAgo->copy()->startOfMonth(),
                $threeMonthsAgo->copy()->endOfMonth(),
            ])
            ->where(function (Builder $query) {
                $query->whereNull('membership_end')
                    ->orWhere('membership_end', '>=', now());
            })
            ->count();

        $retentionRate = ($stillActive / $cohort) * 100;

        // 80%+ retention = 100 score, linear scale down to 0% = 0 score
        return min(100, ($retentionRate / 80) * 100);
    }

    /**
     * Revenue Growth (25% weight).
     */
    protected function calculateRevenueGrowthScore(int $gymId): float
    {
        $thisMonth = MemberPayment::query()->where('gym_id', $gymId)
            ->where('status', PaymentStatus::COMPLETED)
            ->whereMonth('payment_date', now()->month)
            ->sum('amount');

        $lastMonth = MemberPayment::query()->where('gym_id', $gymId)
            ->where('status', PaymentStatus::COMPLETED)
            ->whereMonth('payment_date', now()->subMonth()->month)
            ->sum('amount');

        if ($lastMonth == 0) {
            return $thisMonth > 0 ? 100 : 0;
        }

        $growthRate = (($thisMonth - $lastMonth) / $lastMonth) * 100;

        // 10%+ growth = 100, 0% = 50, -10% = 0
        if ($growthRate >= 10) {
            return 100;
        } elseif ($growthRate >= 0) {
            return 50 + ($growthRate / 10) * 50;
        }

        return max(0, 50 + ($growthRate / 10) * 50);

    }

    /**
     * Lead Conversion (20% weight).
     */
    protected function calculateConversionScore(int $gymId): float
    {
        $totalLeads = Lead::query()->where('gym_id', $gymId)
            ->whereMonth('created_at', now()->month)
            ->count();

        $convertedLeads = Lead::query()->where('gym_id', $gymId)
            ->whereMonth('created_at', now()->month)
            ->where('status', 'converted')
            ->count();

        if ($totalLeads === 0) {
            return 0;
        }

        $conversionRate = ($convertedLeads / $totalLeads) * 100;

        // 30%+ conversion = 100 score
        return min(100, ($conversionRate / 30) * 100);
    }

    /**
     * Attendance Consistency (15% weight).
     */
    protected function calculateAttendanceScore(int $gymId): float
    {
        $activeMembers = GymMember::query()->where('gym_id', $gymId)
            ->where(function (Builder $query) {
                $query->whereNull('membership_end')
                    ->orWhere('membership_end', '>=', now());
            })
            ->count();

        $uniqueAttendees = Attendances::query()->where('gym_id', $gymId)
            ->whereMonth('check_in_time', now()->month)
            ->where('attendanceable_type', GymMember::class)
            ->distinct('attendanceable_id')
            ->count('attendanceable_id');

        if ($activeMembers === 0) {
            return 0;
        }

        $attendanceRate = ($uniqueAttendees / $activeMembers) * 100;

        // 70%+ attendance = 100 score
        return min(100, ($attendanceRate / 70) * 100);
    }

    /**
     * Payment Collection Rate (15% weight).
     */
    protected function calculateCollectionScore(int $gymId): float
    {
        $totalDue = MemberPayment::query()->where('gym_id', $gymId)
            ->whereMonth('payment_date', now()->month)
            ->sum('amount');

        $collected = MemberPayment::query()->where('gym_id', $gymId)
            ->whereMonth('payment_date', now()->month)
            ->where('status', PaymentStatus::COMPLETED)
            ->sum('amount');

        if ($totalDue == 0) {
            return 0; // No dues = perfect score
        }

        $collectionRate = ($collected / $totalDue) * 100;

        // 95%+ collection = 100 score
        return min(100, ($collectionRate / 95) * 100);
    }

    /**
     * Get letter grade.
     */
    protected function getGrade(float $score): string
    {
        return match (true) {
            $score >= 90 => 'A+',
            $score >= 80 => 'A',
            $score >= 70 => 'B',
            $score >= 60 => 'C',
            $score >= 50 => 'D',
            default => 'F',
        };
    }

    /**
     * Get health status.
     */
    protected function getStatus(float $score): string
    {
        return match (true) {
            $score >= 80 => 'excellent',
            $score >= 60 => 'good',
            $score >= 40 => 'fair',
            default => 'poor',
        };
    }
}
