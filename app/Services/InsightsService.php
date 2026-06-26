<?php

namespace App\Services;

use App\Models\MemberPayment;
use App\Models\ReportGoal;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;

class InsightsService
{
    public function __construct(
        protected ForecastService $forecastService,
        protected ChurnRiskService $churnRiskService,
        protected LeadScoringService $leadScoringService
    ) {}

    public function getDailyInsights(int $gymId): Collection
    {
        $insights = collect();

        // 1. Churn Risk Insight
        $churnStats = $this->churnRiskService->getChurnStats($gymId);
        if ($churnStats['at_risk_count'] > 0) {
            $insights->push([
                'type' => 'warning',
                'title' => 'Retention Risk Detected',
                'message' => "{$churnStats['at_risk_count']} members are showing signs of potential churn. Check the Member Reports to intervene.",
                'action_label' => 'View At-Risk Members',
                'action_url' => route('filament.admin.reports.pages.member-reports', ['tenant' => Filament::getTenant()]),
                'icon' => 'heroicon-o-exclamation-triangle',
                'color' => 'danger',
            ]);
        }

        // 2. Revenue Forecast Insight
        $forecast = $this->forecastService->forecastRevenue($gymId, 1);
        if (filled($forecast)) {
            $nextMonthProjection = $forecast[0]['value'];
            // Simple comparison to last month
            $lastMonthRevenue = MemberPayment::where('gym_id', $gymId)
                ->where('status', 'completed')
                ->whereMonth('payment_date', now()->subMonth()->month)
                ->sum('amount');

            if ($nextMonthProjection > $lastMonthRevenue * 1.1) {
                $insights->push([
                    'type' => 'opportunity',
                    'title' => 'Revenue Growth Projected',
                    'message' => 'Next month is projected to hit ' . number_format($nextMonthProjection, 2) . ', a potential increase over last month.',
                    'action_label' => 'View Forecast',
                    'action_url' => route('filament.admin.reports.pages.revenue-reports', ['tenant' => Filament::getTenant()]),
                    'icon' => 'heroicon-o-arrow-trending-up',
                    'color' => 'success',
                ]);
            }
        }

        // 3. Lead Conversion Opportunity
        $leadStats = $this->leadScoringService->getLeadStats($gymId);
        if ($leadStats['hot_leads'] > 3) {
            $insights->push([
                'type' => 'info',
                'title' => 'Hot Leads Pipeline',
                'message' => "You have {$leadStats['hot_leads']} hot leads ready for conversion. Prioritize following up with them today.",
                'action_label' => 'View Hot Leads',
                'action_url' => route('filament.admin.reports.pages.lead-reports', ['tenant' => Filament::getTenant()]),
                'icon' => 'heroicon-o-fire',
                'color' => 'primary',
            ]);
        }

        // 4. Goal Tracking
        $faillingGoals = ReportGoal::where('gym_id', $gymId)
            ->active()
            ->current()
            ->get()
            ->filter(fn ($goal) => $goal->progress < 90 && $goal->days_left < 7);

        if ($faillingGoals->isNotEmpty()) {
            $goalName = $faillingGoals->first()->metric_type;
            $insights->push([
                'type' => 'urgent',
                'title' => 'Goal At Risk',
                'message' => "Your {$goalName} goal is behind schedule with less than 7 days remaining.",
                'action_label' => 'Review Goals',
                'action_url' => '#', // Anchor to goal widget?
                'icon' => 'heroicon-o-flag',
                'color' => 'warning',
            ]);
        }

        return $insights;
    }
}
