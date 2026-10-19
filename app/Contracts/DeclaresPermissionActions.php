<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * For a Filament class whose permissions are not all of {@see \App\Contracts\HasFilamentPermissions}'s
 * shared action list.
 *
 * Most resources are covered by the default list in
 * `config/filament-permissions.php::resource_actions`. A resource with a
 * bespoke ability — `archive`/`unarchive` on a gym member, for instance — needs
 * a way to declare that ability as its own WITHOUT those names leaking into the
 * generated permission set for every other resource. Declaring them globally
 * minted `archive_<slug>`/`unarchive_<slug>` for ~36 resources, of which only
 * `gym_member` had a policy that consumed them.
 *
 * Implemented separately from {@see HasFilamentPermissions} rather than added to
 * it, because that interface already has around forty implementors and an
 * optional capability must not become a breaking change for any of them.
 */
interface DeclaresPermissionActions
{
    /**
     * Actions this class owns, in addition to the shared default list.
     *
     * Returned permission names are `{action}_{derived_slug}`, matching how
     * RolesAndPermissionsService derives slugs for the shared list.
     *
     * @return array<int, string>
     */
    public function getPermissionActions(): array;
}
