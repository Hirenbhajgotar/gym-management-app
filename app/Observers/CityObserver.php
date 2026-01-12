<?php

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\City;

class CityObserver
{
    /**
     * Handle the City "created" event.
     */
    public function created(City $city): void
    {
        $this->clearCache($city);
    }

    /**
     * Handle the City "updated" event.
     */
    public function updated(City $city): void
    {
        $this->clearCache($city);
    }

    /**
     * Handle the City "deleted" event.
     */
    public function deleted(City $city): void
    {
        $this->clearCache($city);
    }

    /**
     * Handle the City "restored" event.
     */
    public function restored(City $city): void
    {
        $this->clearCache($city);
    }

    /**
     * Handle the City "force deleted" event.
     */
    public function forceDeleted(City $city): void
    {
        $this->clearCache($city);
    }

    private function clearCache(City $city): void
    {
        ParserHelper::clearCache(City::CITIES_OF_STATE_CACHE_KEY . $city->state_id);
    }
}
