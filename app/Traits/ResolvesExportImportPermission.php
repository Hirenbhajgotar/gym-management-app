<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\DefaultRole;
use Illuminate\Support\Facades\Gate;
use ReflectionClass;
use Throwable;

trait ResolvesExportImportPermission
{
    /**
     * @var array<int, string>
     */
    protected array $allowedRoles = [
        DefaultRole::SUPER_ADMIN->value,
        DefaultRole::ADMIN->value,
        DefaultRole::GYM_OWNER->value,
    ];

    /**
     * @param  array<int, string>  $roles  Array of role names
     */
    public function allowedRoles(array $roles): static
    {
        $this->allowedRoles = array_unique(array_merge($this->allowedRoles, $roles));

        return $this;
    }

    /**
     * Set up both authorize() and visible() closures using the shared permission
     * resolution logic. Call this inside setUp() after the exporter/importer
     * has been configured.
     *
     * @param  string  $prefix  Permission prefix: 'export' or 'import'
     * @param  string  $propertyName  Property name on the parent class: 'exporter' or 'importer'
     */
    protected function setUpPermission(string $prefix, string $propertyName): void
    {
        $this->authorize(function () use ($prefix, $propertyName): bool {
            return $this->isActionAuthorized($prefix, $propertyName);
        });

        $this->visible(function () use ($prefix, $propertyName): bool {
            return $this->isActionAuthorized($prefix, $propertyName);
        });
    }

    /**
     * Shared authorization check used by both authorize() and visible() closures.
     */
    private function isActionAuthorized(string $prefix, string $propertyName): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole($this->allowedRoles)) {
            return true;
        }

        $permission = $this->resolvePermission($prefix, $propertyName);

        if (! $permission) {
            return false;
        }

        return $user->can($permission);
    }

    /**
     * Resolve a permission key from exporter/importer → model → policy → $tableName.
     */
    private function resolvePermission(string $prefix, string $propertyName): ?string
    {
        $class = $this->resolveActionClass($propertyName);

        if (! $class || ! class_exists($class)) {
            return null;
        }

        /** @var class-string $modelClass */
        $modelClass = $class::getModel();

        if (! $modelClass || ! class_exists($modelClass)) {
            return null;
        }

        /** @var object|null $policy */
        $policy = Gate::getPolicyFor($modelClass);

        if ($policy === null || ! method_exists($policy, 'getTableName')) {
            return null;
        }

        /** @var string $tableName */
        $tableName = $policy::getTableName();

        return "{$prefix}_{$tableName}";
    }

    /**
     * Safely resolve the exporter or importer class string from Filament internals.
     *
     * Tries the getter method (getExporter / getImporter) first, then falls back
     * to reflecting the protected property on the class hierarchy.
     */
    private function resolveActionClass(string $propertyName): ?string
    {
        // Try conventional getter (e.g. getExporter(), getImporter())
        $getter = 'get' . ucfirst($propertyName);

        if (method_exists($this, $getter)) {
            $value = $this->{$getter}();

            return is_string($value) ? $value : (is_object($value) ? $value::class : null);
        }

        // Fallback: read protected property via reflection
        try {
            /** @var ReflectionClass<$this> $ref */
            $ref = new ReflectionClass($this);
            $current = $ref;
            $property = null;

            while ($current) {
                if ($current->hasProperty($propertyName)) {
                    $property = $current->getProperty($propertyName);
                    break;
                }
                /** @var ReflectionClass<$this>|false $parent */
                $parent = $current->getParentClass();
                $current = $parent ?: null;
            }

            if (! $property) {
                return null;
            }

            $property->setAccessible(true);
            $value = $property->getValue($this);

            return is_string($value) ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }
}
