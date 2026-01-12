<?php

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\State;

class StateObserver
{
    /**
     * Handle the State "created" event.
     */
    public function created(State $state): void
    {
        $this->clearCache($state);
    }

    /**
     * Handle the State "updated" event.
     */
    public function updated(State $state): void
    {
        $this->clearCache($state);
    }

    /**
     * Handle the State "deleted" event.
     */
    public function deleted(State $state): void
    {
        $this->clearCache($state);
    }

    /**
     * Handle the State "restored" event.
     */
    public function restored(State $state): void
    {
        $this->clearCache($state);
    }

    /**
     * Handle the State "force deleted" event.
     */
    public function forceDeleted(State $state): void
    {
        $this->clearCache($state);
    }

    private function clearCache(State $state): void
    {
        ParserHelper::clearCache(State::STATES_OF_COUNTRY_CACHE_KEY . $state->country_id);
    }
}
