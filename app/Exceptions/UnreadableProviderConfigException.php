<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A provider-backed device attempted to change its provider policy while the
 * stored `provider_config` ciphertext cannot be decrypted.
 *
 * The payload may still be recoverable with a backup or an old APP_KEY, so the
 * write must be refused rather than silently re-encrypting an empty config over
 * it. Callers distinguish this domain refusal from an unexpected runtime error.
 */
final class UnreadableProviderConfigException extends RuntimeException
{
    public static function whileChangingProviderType(): self
    {
        return new self('Cannot change provider type while provider_config is unreadable without replacing the config.');
    }
}
