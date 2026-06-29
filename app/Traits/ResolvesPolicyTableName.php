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
        $policy = Gate::getPolicyFor($modelClass);

        if (! $policy || ! method_exists($policy, 'getTableName')) {
            return null;
        }

        return $policy::getTableName();
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
