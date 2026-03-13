<?php

declare(strict_types=1);

namespace App\Permissions;

use Illuminate\Cache\CacheManager;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\PermissionRegistrar;

/**
 * Custom Permission Registrar that loads only current gym's permissions.
 *
 * Problem: Default Spatie loads ALL 35k+ permissions globally, causing memory exhaustion.
 * Solution: Override getPermissionsWithRoles() to only load permissions for current gym's roles.
 */
class TeamPermissionRegistrar extends PermissionRegistrar
{
    protected string $baseCacheKey;

    public function __construct(CacheManager $cacheManager)
    {
        parent::__construct($cacheManager);
        $this->baseCacheKey = $this->cacheKey;
    }

    /**
     * Override to set per-gym cache key when team changes.
     */
    public function setPermissionsTeamId($id): void
    {
        parent::setPermissionsTeamId($id);

        // Update cache key for new team context
        $this->cacheKey = $id
            ? "{$this->baseCacheKey}.gym.{$id}"
            : $this->baseCacheKey;

        // Clear loaded permissions to force reload with new key
        $this->permissions = null;
    }

    /**
     * Override to load only permissions for current context.
     *
     * - With team_id: Load only permissions for that gym's roles (~500)
     * - Without team_id: Load only global roles (gym_id IS NULL) (~750)
     *
     * This prevents loading all 35k+ permissions globally.
     */
    protected function getPermissionsWithRoles(): Collection
    {
        $teamId = $this->getPermissionsTeamId();

        if ($teamId) {
            // Load permissions for specific gym's roles only
            return $this->permissionClass::select()
                ->with(['roles' => function ($query) use ($teamId) {
                    $query->where($this->teamsKey, $teamId);
                }])
                ->whereHas('roles', function ($query) use ($teamId) {
                    $query->where($this->teamsKey, $teamId);
                })
                ->get();
        }

        // For super_admin (no team context): Load only global roles (gym_id IS NULL)
        // This loads ~750 permissions instead of all 35k+
        return $this->permissionClass::select()
            ->with(['roles' => function ($query) {
                $query->whereNull($this->teamsKey);
            }])
            ->whereHas('roles', function ($query) {
                $query->whereNull($this->teamsKey);
            })
            ->get();
    }

    /**
     * Clear permission cache for a specific gym.
     */
    public function forgetCachedPermissionsForGym(int $gymId): bool
    {
        $cacheKey = "{$this->baseCacheKey}.gym.{$gymId}";

        return $this->getCacheStore()->forget($cacheKey);
    }

    /**
     * Initialize cache with per-gym key.
     */
    public function initializeCache(): void
    {
        parent::initializeCache();
        $this->baseCacheKey = $this->cacheKey;

        // Set cache key based on current team context
        $teamId = $this->getPermissionsTeamId();
        if ($teamId) {
            $this->cacheKey = "{$this->baseCacheKey}.gym.{$teamId}";
        }
    }
}
