<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GymMember;
use Illuminate\Database\Eloquent\Collection;

class ChurnRiskService
{
    /**
     * Get members at risk of churning.
     *
     * @param  int  $thresholdScore  Score above which a member is considered at risk (0-100)
     */
    public function getAtRiskMembers(int $gymId, int $thresholdScore = 65, int $limit = 50): Collection
    {
        // Get active members
        $members = GymMember::query()->where('gym_id', $gymId)
            ->with(['latestPayment', 'lastAttendance'])
            ->membershipActive()
            ->get();

        // Calculate risk score for each member
        $atRiskMembers = $members->map(function ($member) {
            $score = $this->calculateRiskScore($member);
            $member->risk_score = $score;
            $member->risk_factors = $this->identifyRiskFactors($member);

            return $member;
        })->filter(function ($member) use ($thresholdScore) {
            return $member->risk_score >= $thresholdScore;
        })->sortByDesc('risk_score')
            ->take($limit);

        return $atRiskMembers;
    }

    /**
     * Calculate individual churn risk score (0-100).
     * Higher score = Higher risk.
     */
    public function calculateRiskScore(GymMember $member): int
    {
        $score = 0;

        // Factor 1: Attendance Recency (Max 40 points)
        $lastAttendance = $member->lastAttendance?->check_in_time;
        if (! $lastAttendance) {
            // No attendance ever? High risk if joined > 1 month ago
            if ($member->created_at->diffInDays(now()) > 30) {
                $score += 40;
            }
        } else {
            $daysSinceAttendance = $lastAttendance->diffInDays(now());
            if ($daysSinceAttendance > 30) {
                $score += 40;
            } elseif ($daysSinceAttendance > 21) {
                $score += 30;
            } elseif ($daysSinceAttendance > 14) {
                $score += 15;
            } elseif ($daysSinceAttendance > 7) {
                $score += 5;
            }
        }

        // Factor 2: Payment History (Max 30 points)
        // If last payment failed or was late
        $lastPayment = $member->latestPayment;
        if ($lastPayment && $lastPayment->status === 'failed') {
            $score += 30;
        }

        // Factor 3: Membership Expiry (Max 30 points)
        $expiryDate = $member->membership_end;
        if ($expiryDate) {
            $daysToExpiry = now()->diffInDays($expiryDate, false);
            if ($daysToExpiry < 0) {
                $score += 30;
            } // Already expired
            elseif ($daysToExpiry <= 7) {
                $score += 20;
            } elseif ($daysToExpiry <= 30) {
                $score += 10;
            }
        }

        return min(100, $score);
    }

    /**
     * Identify specific risk factors for reporting.
     */
    protected function identifyRiskFactors(GymMember $member): array
    {
        $factors = [];

        $lastAttendance = $member->lastAttendance?->check_in_time;
        if (! $lastAttendance) {
            if ($member->created_at->diffInDays(now()) > 30) {
                $factors[] = 'No attendance recorded';
            }
        } else {
            $daysSinceAttendance = $lastAttendance->diffInDays(now());
            if ($daysSinceAttendance > 14) {
                $factors[] = "Absent for {$daysSinceAttendance} days";
            }
        }

        $expiryDate = $member->membership_end;
        if ($expiryDate && now()->diffInDays($expiryDate, false) <= 14) {
            $factors[] = 'Membership expiring soon';
        }

        if ($member->latestPayment?->status === 'failed') {
            $factors[] = 'Last payment failed';
        }

        return $factors;
    }

    /**
     * Get aggregate churn statistics.
     */
    /**
     * Get aggregate churn statistics.
     */
    public function getChurnStats(int $gymId): array
    {
        // Get all active members with necessary relationships
        $members = GymMember::query()->where('gym_id', $gymId)
            ->membershipActive()
            ->with(['lastAttendance', 'membershipPlan', 'latestPayment'])
            ->get();

        $stats = [
            'total_at_risk' => 0,
            'high_risk' => 0,
            'high_risk_value' => 0,
            'high_risk_avg_days' => 0,
            'medium_risk' => 0,
            'medium_risk_value' => 0,
            'medium_risk_avg_days' => 0,
            'low_risk' => 0,
            'low_risk_value' => 0,
            'low_risk_avg_days' => 0,
        ];

        $highRiskDaysSum = 0;
        $mediumRiskDaysSum = 0;
        $lowRiskDaysSum = 0;

        foreach ($members as $member) {
            $score = $this->calculateRiskScore($member);
            $daysSinceVisit = $member->lastAttendance
                ? $member->lastAttendance->check_in_time->diffInDays(now())
                : $member->created_at->diffInDays(now());

            // Get membership value (monthly equivalent or plan price)
            $value = $member->membershipPlan?->price ?? 0;

            if ($score >= 65) {
                $stats['high_risk']++;
                $stats['high_risk_value'] += $value;
                $highRiskDaysSum += $daysSinceVisit;
            } elseif ($score >= 30) {
                $stats['medium_risk']++;
                $stats['medium_risk_value'] += $value;
                $mediumRiskDaysSum += $daysSinceVisit;
            } else {
                $stats['low_risk']++;
                $stats['low_risk_value'] += $value;
                $lowRiskDaysSum += $daysSinceVisit;
            }
        }

        $stats['total_at_risk'] = $stats['high_risk'] + $stats['medium_risk'] + $stats['low_risk'];

        // Calculate averages
        $stats['high_risk_avg_days'] = $stats['high_risk'] > 0 ? round($highRiskDaysSum / $stats['high_risk']) : 0;
        $stats['medium_risk_avg_days'] = $stats['medium_risk'] > 0 ? round($mediumRiskDaysSum / $stats['medium_risk']) : 0;
        $stats['low_risk_avg_days'] = $stats['low_risk'] > 0 ? round($lowRiskDaysSum / $stats['low_risk']) : 0;

        // At-risk count is only high-risk members (score >= 65)
        $stats['at_risk_count'] = $stats['high_risk'];
        $stats['predicted_churn_rate'] = $members->count() > 0
            ? round(($stats['high_risk'] / $members->count()) * 100, 1)
            : 0;

        return $stats;
    }
}
