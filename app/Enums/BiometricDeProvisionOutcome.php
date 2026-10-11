<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Outcome of requesting hardware erasure of a biometric template.
 *
 * `Deleted` is written only when the terminal confirms `DATA DELETE user Pin=N`, or when the
 * template provably never reached the hardware and so has no credential to erase. Every other
 * outcome leaves the obligation outstanding, and an outstanding obligation always has a row
 * state (`PendingDeletion`) that the nightly reconciler revisits.
 */
enum BiometricDeProvisionOutcome: string
{
    /** Never reached the hardware, so there is no credential to erase. */
    case NotDelivered = 'not_delivered';

    /** The PIN is shared with another active template on the same device and must be retained. */
    case DeferredToSibling = 'deferred_to_sibling';

    /** A `user_delete` was created and is waiting to be dequeued. */
    case Queued = 'queued';

    /** A `user_delete` for this PIN is already pending or in flight. */
    case AlreadyQueued = 'already_queued';

    /** A `FAILED` `user_delete` below the attempt cap was returned to the queue in place. */
    case ReDriven = 're_driven';

    /** The `user_delete` for this PIN has exhausted its delivery budget and needs an operator. */
    case Exhausted = 'exhausted';

    /** Device row is missing or is not ADMS-enabled, so no command can be emitted yet. */
    case DeviceUnavailable = 'device_unavailable';
}
