<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Gym;
use App\Models\User;

class GymPolicy extends BasePolicy
{
    protected static string $tableName = 'gym';

    /**
     * Deleting a gym hard-deletes its biometric terminals, access-control history and
     * erasure register at the database level, so the model `delete()`, the credential
     * tombstone, the erasure obligations and the deletion audit never run. Per-device
     * deletion and an operator discharge are the audited paths, so a gym with terminals or
     * outstanding obligations is not deletable.
     */
    public function delete(User $user, mixed $model): bool
    {
        if ($model instanceof Gym) {
            if ($model->biometricDevices()->exists()) {
                return false;
            }

            // Read the same predicate as the operator surface: the flag alone missed a
            // flagless `DeletionUnresolvable` row and a cloud `PendingDeletion` row during
            // a provider outage, so the tenant cascade could destroy the register.
            if ($model->hasOutstandingErasureObligations()) {
                return false;
            }
        }

        return parent::delete($user, $model);
    }
}
