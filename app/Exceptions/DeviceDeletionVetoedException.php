<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A `deleting` listener halted a terminal deletion by returning `false`.
 *
 * Eloquent reports that halt as a normal `false` return from `delete()`, which is
 * indistinguishable from a delete that succeeded and returned nothing useful. The
 * tombstone hook has already written in that path, so `BiometricDevice::delete()`
 * raises this inside the transaction to force a rollback and only then reports
 * the failure to the caller as `false`.
 */
final class DeviceDeletionVetoedException extends RuntimeException
{
    public static function whileDeleting(): self
    {
        return new self('A deleting listener vetoed the terminal deletion; the transaction was rolled back.');
    }
}
