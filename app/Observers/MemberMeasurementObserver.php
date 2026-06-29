<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\MemberMeasurement;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MemberMeasurementObserver
{
    /**
     * Handle the MemberMeasurement "created" event.
     */
    public function created(MemberMeasurement $memberMeasurement): void
    {
        $this->clearChartCache($memberMeasurement);
    }

    /**
     * Handle the MemberMeasurement "updated" event.
     */
    public function updated(MemberMeasurement $memberMeasurement): void
    {
        $this->clearChartCache($memberMeasurement);
    }

    /**
     * Handle the MemberMeasurement "deleted" event.
     */
    public function deleted(MemberMeasurement $memberMeasurement): void
    {
        $this->clearChartCache($memberMeasurement);
    }

    /**
     * Handle the MemberMeasurement "restored" event.
     */
    public function restored(MemberMeasurement $memberMeasurement): void
    {
        $this->clearChartCache($memberMeasurement);
    }

    /**
     * Handle the MemberMeasurement "force deleted" event.
     */
    public function forceDeleted(MemberMeasurement $memberMeasurement): void
    {
        $this->clearChartCache($memberMeasurement);
    }

    /**
     * Clear the chart cache for the associated member.
     */
    protected function clearChartCache(MemberMeasurement $memberMeasurement): void
    {
        if ($memberMeasurement->gym_member_id) {
            $key = "member_weight_progress_{$memberMeasurement->gym_member_id}";
            Cache::forget($key);
            Log::info("Cleared chart cache for member: {$memberMeasurement->gym_member_id}");
        }
    }
}
