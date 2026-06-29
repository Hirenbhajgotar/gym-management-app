<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\DefaultRole;
// use App\Models\Filament\Actions\Imports\Models\Import;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Gate;

class ImportPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([DefaultRole::SUPER_ADMIN->value, DefaultRole::ADMIN->value, DefaultRole::GYM_OWNER->value]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Import $import): bool
    {
        if (! $import->user()->is($user)) {
            return false;
        }

        $key = $this->resolveKey($import->importer, 'import');

        return $key && $user->can($key);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Import $import): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Import $import): bool
    {
        return $import->user()->is($user) || $user->hasAnyRole([DefaultRole::SUPER_ADMIN->value, DefaultRole::ADMIN->value, DefaultRole::GYM_OWNER->value]);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Import $import): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Import $import): bool
    {
        return $user->hasAnyRole([DefaultRole::SUPER_ADMIN->value, DefaultRole::ADMIN->value, DefaultRole::GYM_OWNER->value]);
    }

    private function resolveKey(?string $importerClass, string $action): ?string
    {
        if (! $importerClass || ! class_exists($importerClass)) {
            return null;
        }

        // 1. Get the model class from importer
        $modelClass = $importerClass::getModel();

        if (! $modelClass || ! class_exists($modelClass)) {
            return null;
        }

        // 2. Get the registered policy for that model
        $policy = Gate::getPolicyFor($modelClass);

        if (! $policy) {
            return null;
        }

        if (! method_exists($policy, 'getTableName')) {
            return null;
        }
        // 3. Read $tableName from the policy
        $tableName = $policy::getTableName();

        if (! $tableName) {
            return null;
        }

        return "{$action}_{$tableName}";
        // e.g. "export_gym_member", "export_manager"
    }
}
