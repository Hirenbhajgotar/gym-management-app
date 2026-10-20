<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\BiometricTemplate;

/**
 * Which side of ADR-004's dual-mode device owns a credential.
 *
 * A device-native (terminal) credential and a cloud/provider credential on one PIN are two
 * different credentials. `Superseded` is excluded from every live-credential predicate and
 * from the erasure reconciler, so a writer must only ever demote a row of its own class.
 */
enum BiometricProvenanceEnum: string
{
    case Hardware = 'hardware';
    case Cloud = 'cloud';

    public static function forProviderType(?string $providerType): self
    {
        return in_array($providerType, [...BiometricTemplate::HARDWARE_PROVIDER_TYPES, null], true)
            ? self::Hardware
            : self::Cloud;
    }
}
