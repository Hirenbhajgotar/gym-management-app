<?php

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\Country;

class CountryObserver
{
    /**
     * Handle the Country "created" event.
     */
    public function created(Country $country): void
    {
        $this->clearCache($country);
    }

    /**
     * Handle the Country "updated" event.
     */
    public function updated(Country $country): void
    {
        $this->clearCache($country);
    }

    /**
     * Handle the Country "deleted" event.
     */
    public function deleted(Country $country): void
    {
        $this->clearCache($country);
    }

    /**
     * Handle the Country "restored" event.
     */
    public function restored(Country $country): void
    {
        $this->clearCache($country);
    }

    /**
     * Handle the Country "force deleted" event.
     */
    public function forceDeleted(Country $country): void
    {
        $this->clearCache($country);
    }

    private function clearCache(Country $country): void
    {
        ParserHelper::clearCache(Country::COUNTRIES_LIST_CACHE_KEY);
    }
}
