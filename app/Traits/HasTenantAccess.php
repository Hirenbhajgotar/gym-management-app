<?php

namespace App\Traits;

use App\Models\Gym;
use Filament\Facades\Filament;

/**
 * Trait for type-safe tenant access in Filament components
 */
trait HasTenantAccess
{
    /**
     * Get the current gym tenant with proper type hinting
     */
    protected function getGym(): Gym
    {
        /** @var Gym $gym */
        $gym = Filament::getTenant();

        return $gym;
    }

    /**
     * Get the current gym ID
     */
    protected function getGymId(): int|string
    {
        return $this->getGym()->id;
    }
}
