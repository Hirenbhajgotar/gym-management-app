<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Facades\Gate;
use ReflectionClass;
use ReflectionException;

trait ResolvesPolicyTableName
{
    public function resolvePolicyTableName(string $modelClass): ?string
    {
        /** @var object|null $policy */
        $policy = Gate::getPolicyFor($modelClass);

        if ($policy === null || ! method_exists($policy, 'getTableName')) {
            return null;
        }

        /** @var string $tableName */
        $tableName = $policy::getTableName();

        return $tableName;
    }

    protected function safeReflection(string $className): ?ReflectionClass
    {
        try {
            return new ReflectionClass($className);
        } catch (ReflectionException) {
            return null;
        }
    }
}
