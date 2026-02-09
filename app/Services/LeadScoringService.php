<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Collection;

class LeadScoringService
{
    /**
     * Get scored leads sorted by score.
     */
    public function getScoredLeads(int $gymId, int $limit = 50): Collection
    {
        // Get active leads (not converted/lost)
        $leads = Lead::where('gym_id', $gymId)
            ->whereNotIn('status', [\App\Enums\LeadStatus::CONVERTED, \App\Enums\LeadStatus::LOST])
            ->with(['followUps', 'membershipPlan', 'creator'])
            ->get();

        return $leads->map(function ($lead) {
            $lead->score = $this->calculateScore($lead);
            $lead->score_factors = $this->identifyScoreFactors($lead);
            return $lead;
        })
        ->sortByDesc('score')
        ->take($limit);
    }

    /**
     * Calculate lead score (0-100).
     */
    public function calculateScore(Lead $lead): int
    {
        $score = 0;

        // 1. Source Quality (Max 25)
        // Adjust these based on your actual source enum values
        $sourceScores = [
            'referral' => 25,
            'organic_search' => 20,
            'walk_in' => 20,
            'social_media' => 15,
            'paid_ad' => 10,
            'cold_outreach' => 5,
        ];
        
        $source = $lead->source?->value ?? 'unknown';
        $score += $sourceScores[$source] ?? 10;

        // 2. Engagement / Follow-ups (Max 30)
        $followUpCount = $lead->follow_ups_count ?? $lead->followUps()->count();
        $score += min(30, $followUpCount * 10); // 10 points per follow-up, max 3

        // 3. Recency (Max 20)
        // Recently created or updated leads are hotter
        $daysSinceUpdate = $lead->updated_at->diffInDays(now());
        if ($daysSinceUpdate <= 2) $score += 20;
        elseif ($daysSinceUpdate <= 7) $score += 10;
        elseif ($daysSinceUpdate <= 14) $score += 5;

        // 4. Intent / Completeness (Max 25)
        if ($lead->membership_plan_id) $score += 10; // Known desired plan
        if (!empty($lead->interest)) $score += 10; // Specific interests
        if ($lead->phone && $lead->email) $score += 5; // Complete contact info

        return min(100, $score);
    }

    /**
     * Identify reasons for the score.
     */
    protected function identifyScoreFactors(Lead $lead): array
    {
        $factors = [];

        if ($lead->source?->value === 'referral') {
            $factors[] = 'Referral source (+25)';
        }

        $followUpCount = $lead->follow_ups_count ?? $lead->followUps()->count();
        if ($followUpCount > 0) {
            $factors[] = "{$followUpCount} interactions (+".(min(30, $followUpCount * 10)).")";
        }

        if ($lead->membership_plan_id) {
            $factors[] = 'Plan selected (+10)';
        }
        
        if ($lead->updated_at->diffInDays(now()) <= 2) {
            $factors[] = 'Recently active (+20)';
        }

        return $factors;
    }

    /**
     * Get aggregate lead stats.
     */
    public function getLeadStats(int $gymId): array
    {
        $scoredLeads = $this->getScoredLeads($gymId);
        $hotLeads = $scoredLeads->where('score', '>=', 70)->count();
        $warmLeads = $scoredLeads->where('score', '>=', 40)->where('score', '<', 70)->count();

        return [
            'total_active' => $scoredLeads->count(),
            'hot_leads' => $hotLeads,
            'warm_leads' => $warmLeads,
        ];
    }
}
