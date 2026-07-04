<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Gym;
use Filament\Facades\Filament;
use RuntimeException;

/**
 * Trait for type-safe tenant access in Filament components
 */
trait HasTenantAccess
{
    /**
     * Get the current gym tenant with proper type hinting
     */
    public function getGym(): ?Gym
    {
        /** @var Gym|null $gym */
        $gym = Filament::getTenant();

        return $gym;
    }

    /**
     * Get the current gym ID
     */
    public function getGymId(): int|string
    {
        /** @var Gym|null $gym */
        $gym = $this->getGym();

        if (! $gym) {
            throw new RuntimeException('No tenant available');
        }

        return $gym->id;
    }

    /**
     * Get the current gym slug
     */
    public function getTenantSlug(): ?string
    {
        /** @var Gym|null $gym */
        $gym = $this->getGym();

        if (! $gym) {
            throw new RuntimeException('No tenant available');
        }

        return $gym->slug;
    }
}
