<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated Only used by the deprecated {@see \App\Models\PermissionSetting} model.
 *             Will be removed when PermissionSetting is deleted.
 */
class DefaultResourcesCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value ? json_decode($value, true) : ['resources' => [], 'widgets' => [], 'reports' => [], 'other' => []];
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $structuredValue = [
            'resources' => $value['resources'] ?? [],
            'widgets' => $value['widgets'] ?? [],
            'reports' => $value['reports'] ?? [],
            'other' => $value['other'] ?? [],
        ];

        return json_encode($structuredValue);
    }
}
