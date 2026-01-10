<?php

namespace App\Observers;

use App\Helpers\ParserHelper;
use App\Models\Trainer;

class TrainerObserver
{
    /**
     * Handle the Trainer "created" event.
     */
    public function created(Trainer $trainer): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Trainer "updated" event.
     */
    public function updated(Trainer $trainer): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Trainer "deleted" event.
     */
    public function deleted(Trainer $trainer): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Trainer "restored" event.
     */
    public function restored(Trainer $trainer): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Trainer "force deleted" event.
     */
    public function forceDeleted(Trainer $trainer): void
    {
        $this->clearCache();
    }

    private function clearCache(): void
    {
        if (auth()->check()) {
            ParserHelper::clearCache('trainer_navigation_badge_count_' . auth()->id());
        }
    }
}
