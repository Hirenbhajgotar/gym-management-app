<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The row changed after the backfill took its snapshot, or it no longer
 * satisfies the cleanup policy that selected it.
 *
 * A stale snapshot must never win over a concurrent edit: the command reports
 * the device as a conflict and leaves the row untouched instead of deleting a
 * credential or verifier that no longer belongs to the snapshot.
 */
final class StaleDeviceSnapshotException extends RuntimeException {}
