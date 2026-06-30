<?php

declare(strict_types=1);

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
        $this->clearCache();
    }

    /**
     * Handle the Country "updated" event.
     */
    public function updated(Country $country): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Country "deleted" event.
     */
    public function deleted(Country $country): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Country "restored" event.
     */
    public function restored(Country $country): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Country "force deleted" event.
     */
    public function forceDeleted(Country $country): void
    {
        $this->clearCache();
    }

    private function clearCache(): void
    {
        ParserHelper::clearCache(Country::COUNTRIES_LIST_CACHE_KEY);
    }
}
