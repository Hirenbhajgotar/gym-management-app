<?php

declare(strict_types=1);

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
    public function getGym(): Gym
    {
        /** @var Gym $gym */
        $gym = Filament::getTenant();

        return $gym;
    }

    /**
     * Get the current gym ID
     */
    public function getGymId(): int|string
    {
        return $this->getGym()->id;

    }

    /**
     * Get the current gym slug
     */
    public function getTenantSlug(): string
    {
        return $this->getGym()->slug;
    }
}
