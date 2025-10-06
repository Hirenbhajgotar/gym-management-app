<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;

class FeedbackHelper
{
    /**
     * Store the user ID in cache to prevent the feedback popup from displaying for a given number of days.
     */
    public static function storeFeedbackCache(?int $userId = null, int $days = 25): void
    {
        if ($userId) {
            Cache::put("feedback_popup_shown_{$userId}", true, now()->addDays($days));
        }
    }

    /**
     * Get the cache key for the given user ID to check if the feedback popup has been shown.
     */
    public static function getFeedbackCacheKey(?int $userId = null): string
    {
        return "feedback_popup_shown_{$userId}";
    }
}
