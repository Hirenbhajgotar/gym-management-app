<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Wire schema a terminal expects for the ADMS user master table.
 *
 * The value is negotiated per terminal and snapshotted on every queued command
 * ({@see \App\Models\AdmsDeviceCommand::$user_table}), so a later configuration
 * change cannot rewrite the format of a command that is already in flight.
 *
 * Parsing is deliberately strict: only `USERINFO` and `user` are recognised, in
 * any casing. A blank, misspelled or otherwise unknown value is *not* treated as
 * the legacy `user` schema — that coercion silently reintroduced the hardware
 * `-1004` failure this schema negotiation exists to remove. Unknown values fall
 * through to the documented default (`USERINFO`), and the boot-time configuration
 * guard logs the misconfiguration.
 */
enum AdmsUserTableEnum: string implements HasLabel
{
    case UserInfo = 'USERINFO';
    case User = 'user';

    public function getLabel(): string
    {
        return match ($this) {
            self::UserInfo => 'USERINFO (Standard / Legacy eSSL & ZKTeco)',
            self::User => 'user (Modern SpeedFace / Linux / Android)',
        };
    }

    /**
     * Parse a configured or stored value, or null when it is not a known schema.
     */
    public static function tryParse(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_scalar($value)) {
            return null;
        }

        return match (strtoupper(trim((string) $value))) {
            'USERINFO' => self::UserInfo,
            'USER' => self::User,
            default => null,
        };
    }

    /**
     * Resolve the first recognised value, falling back to the standard schema.
     *
     * The fallback is `USERINFO` on purpose: it is both the shipped default and
     * the schema the majority of the fleet speaks, so an unreadable or invalid
     * override degrades to the format most terminals accept.
     */
    public static function resolve(mixed ...$candidates): self
    {
        foreach ($candidates as $candidate) {
            $parsed = self::tryParse($candidate);

            if ($parsed !== null) {
                return $parsed;
            }
        }

        return self::UserInfo;
    }
}
