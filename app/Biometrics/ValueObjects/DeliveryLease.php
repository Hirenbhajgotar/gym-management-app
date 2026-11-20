<?php

declare(strict_types=1);

namespace App\Biometrics\ValueObjects;

use App\Models\AdmsDeviceCommand;
use InvalidArgumentException;

/**
 * A leased command paired with the exact wire the terminal must receive.
 *
 * The wire is already serialised and CRLF-terminated so the protocol adapter can
 * return it verbatim without re-deriving the payload or its framing. The leasing
 * transaction has committed before this object exists, so no row lock is held
 * while the terminal is being addressed.
 */
final readonly class DeliveryLease
{
    private function __construct(
        public AdmsDeviceCommand $command,
        public string $wire,
    ) {}

    public static function for(AdmsDeviceCommand $command, string $wire): self
    {
        if ($wire === '' || ! str_ends_with($wire, "\r\n")) {
            throw new InvalidArgumentException('A delivery lease wire must be non-empty and CRLF-terminated.');
        }

        return new self($command, $wire);
    }
}
