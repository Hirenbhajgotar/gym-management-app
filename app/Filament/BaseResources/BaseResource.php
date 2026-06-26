<?php

declare(strict_types=1);

namespace App\Filament\BaseResources;

use App\Contracts\HasFilamentPermissions;
use App\Enums\DefaultRole;
use App\Traits\ConvertsToSnakeCase;
use App\Traits\ResolvesPolicyTableName;
use Filament\Resources\Resource;

abstract class BaseResource extends Resource implements HasFilamentPermissions
{
    use ConvertsToSnakeCase;
    use ResolvesPolicyTableName;

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => [
                'view',
                'view_any',
                'create',
                'delete',
                'delete_any',
                'update',
                'restore',
                'replicate',
                'reorder',
                'export',
                'import',
            ],

            DefaultRole::RECEPTIONIST->value => ['view', 'view_any'],
            DefaultRole::TRAINER->value => ['view', 'view_any'],
        ];
    }

    /**
     * Derive a permission slug from the model's policy $tableName.
     *
     * This matches how RolesAndPermissionsService generates permission names
     * and how BasePolicy generates permission checks. The policy's $tableName
     * is the source of truth for permission naming.
     *
     * Falls back to deriving from the model class name, then the Resource class name.
     */
    public function getPermissionSlug(): string
    {
        $modelClass = static::getModel();
        // Check the policy's $tableName first — it's the source of truth.
        $tableName = $this->resolvePolicyTableName($modelClass);
        if ($tableName !== null) {
            return $tableName;
        }
        // Fallback to deriving from model class name.
        return $this->convertFromClassName(class_basename($modelClass));
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        $reflection = new \ReflectionClass(static::class);
        if ($reflection->isAbstract()) {
            return false;
        }

        $slug = (new static)->getPermissionSlug();

        return $user->hasRole(DefaultRole::SUPER_ADMIN->value) || $user->can("view_any_{$slug}");
    }
}
