<?php

declare(strict_types=1);

namespace App\Filament\BaseWidgets;

use App\Contracts\HasFilamentPermissions;
use App\Enums\DefaultRole;
use App\Traits\ConvertsToSnakeCase;
use Filament\Widgets\StatsOverviewWidget;

abstract class BaseStatsOverviewWidget extends StatsOverviewWidget implements HasFilamentPermissions
{
    use ConvertsToSnakeCase;

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => ['widget'],
            DefaultRole::RECEPTIONIST->value => ['widget'],
            DefaultRole::TRAINER->value => ['widget'],
        ];
    }

    public function getWidgetPermissionSlug(): string
    {
        $className = class_basename(static::class);

        return $this->convertFromClassName($className, 'Widget');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        /** @phpstan-ignore new.static (safe because this is always called on concrete subclasses) */
        $slug = (new static())->getWidgetPermissionSlug();

        return $user->hasRole(DefaultRole::SUPER_ADMIN->value) || $user->can("widget_{$slug}");
    }
}
