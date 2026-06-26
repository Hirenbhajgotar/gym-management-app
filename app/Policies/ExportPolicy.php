<?php

namespace App\Policies;

use App\Enums\DefaultRole;
// use App\Models\Filament\Actions\Exports\Models\Export;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Gate;

class ExportPolicy
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
    public function view(User $user, Export $export): bool
    {
        if (! $export->user()->is($user)) {
            return false;
        }

        $key = $this->resolveKey($export->exporter, 'export');

        return $key && $user->can($key);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Generic fallback — prefer per-action authorize() callback
        // This returns true so per-resource ->authorize() takes full control
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Export $export): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Export $export): bool
    {
        return $export->user()->is($user) || $user->hasAnyRole([DefaultRole::SUPER_ADMIN->value, DefaultRole::ADMIN->value, DefaultRole::GYM_OWNER->value]);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Export $export): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Export $export): bool
    {
        return $user->hasAnyRole([DefaultRole::SUPER_ADMIN->value, DefaultRole::ADMIN->value, DefaultRole::GYM_OWNER->value]);
    }

    public function downloadExport(User $user, Export $export): bool
    {
        return $this->view($user, $export);
    }

    private function resolveKey(?string $exporterClass, string $action): ?string
    {
        if (! $exporterClass || ! class_exists($exporterClass)) {
            return null;
        }

        // 1. Get the model class from exporter
        $modelClass = $exporterClass::getModel();

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
