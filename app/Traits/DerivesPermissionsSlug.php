<?php

namespace App\Traits;

trait DerivesPermissionsSlug
{
    use ConvertsToSnakeCase;
    use ResolvesPolicyTableName;

    public function generatePagePermission(string $slug): string
    {
        $prefix = config('filament-permissions.page_prefix', 'page_');

        return "{$prefix}{$slug}";
    }

    public function generateWidgetPermission(string $slug): string
    {
        $prefix = config('filament-permissions.widget_prefix', 'widget_');

        return "{$prefix}{$slug}";
    }

    /**
     * Resolve the model class name from a Resource's static $model property.
     * Uses reflection because direct static property access via string variable
     * returns null in PHP.
     */
    public function resolveResourceModel(\ReflectionClass $resourceReflection): ?string
    {
        if (! $resourceReflection->hasProperty('model')) {
            return null;
        }

        $prop = $resourceReflection->getProperty('model');
        $prop->setAccessible(true);

        return $prop->getValue();
    }

    /**
     * Derive permission slug from the Model class name when available,
     * falling back to the Resource class name.
     *
     * This ensures generated permissions match BasePolicy's $tableName convention
     * (e.g. WhatsAppCreditTransaction → whatsapp_credit_transaction, not whatsapp_transaction).
     */
    public function deriveSlugFromModelOrResource(?string $modelClass, \ReflectionClass $resourceReflection): string
    {
        if ($modelClass !== null) {
            // Prefer the policy's $tableName — it's the source of truth for permission naming.
            $tableName = $this->resolvePolicyTableName($modelClass);

            if ($tableName !== null) {
                return $tableName;
            }

            // Fallback to deriving from model class name
            $modelReflection = $this->safeReflection($modelClass);

            if ($modelReflection !== null) {
                return $this->convertFromClassName($modelReflection->getShortName(), '');
            }
        }

        return $this->convertFromClassName($resourceReflection->getShortName(), 'Resource');
    }

    public function derivePageSlug(\ReflectionClass $reflection): string
    {
        $prefix = $this->convertFromClassName($reflection->getShortName(), 'Page');

        return $this->generatePagePermission($prefix);
    }

    public function deriveWidgetSlug(\ReflectionClass $reflection): string
    {
        $prefix = $this->convertFromClassName($reflection->getShortName(), 'Widget');

        return $this->generateWidgetPermission($prefix);
    }
}
