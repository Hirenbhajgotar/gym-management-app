<?php

declare(strict_types=1);

namespace App\Filament\BasePages;

use App\Contracts\HasFilamentPermissions;
use App\Enums\DefaultRole;
use App\Traits\ConvertsToSnakeCase;
use Filament\Pages\Page;

abstract class BasePage extends Page implements HasFilamentPermissions
{
    use ConvertsToSnakeCase;

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => ['page'],
            DefaultRole::RECEPTIONIST->value => ['page'],
            DefaultRole::TRAINER->value => ['page'],
        ];
    }

    /**
     * Derive a permission slug from the class name.
     */
    public function getPagePermissionSlug(): string
    {
        $className = class_basename(static::class);

        return $this->convertFromClassName($className, 'Page');
    }

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        $slug = (new static)->getPagePermissionSlug();

        return $user->hasRole(DefaultRole::SUPER_ADMIN->value) || $user->can("page_{$slug}");
    }
}
