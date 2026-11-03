<?php

declare(strict_types=1);

namespace App\Biometrics\ValueObjects;

/**
 * Outcome of stripping the ADMS comm key aliases from a provider_config array.
 *
 * `config` is the rewritten array (the input is never mutated) and
 * `removedAliases` names the keys that were deleted, so rotation and removal can
 * audit exactly what they changed.
 */
final readonly class CommKeyAliasStripping
{
    /**
     * @param  array<array-key, mixed>  $config
     * @param  list<string>  $removedAliases
     */
    public function __construct(
        public array $config,
        public array $removedAliases,
    ) {}
}
