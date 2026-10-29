<?php

declare(strict_types=1);

namespace App\Biometrics\ValueObjects;

/**
 * Stable, typed reasons an ADMS request can fail authentication.
 *
 * The middleware maps these to HTTP status codes, so a typo can no longer
 * silently change a response from 400 to 401.
 */
enum AdmsAuthenticationFailureReason: string
{
    case MissingSerial = 'missing_serial';
    case UnknownOrDisabledDevice = 'unknown_or_disabled_device';
    case Maintenance = 'maintenance';
    case LockedOut = 'locked_out';
    case InvalidOrMissingCommKey = 'invalid_or_missing_comm_key';
}
