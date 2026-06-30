<?php

declare(strict_types=1);

namespace App\Biometrics\DTOs;

readonly class TemplateRef
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $provider,
        public string $externalId,
        public array $meta = []
    ) {}

    public static function fromJson(string $json): self
    {
        /** @var array{provider: string, external_id: string, meta?: array<string, mixed>} $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return new self(
            provider: $data['provider'],
            externalId: $data['external_id'],
            meta: $data['meta'] ?? []
        );
    }

    public function toJson(): string
    {
        return json_encode([
            'provider' => $this->provider,
            'external_id' => $this->externalId,
            'meta' => $this->meta,
        ], JSON_THROW_ON_ERROR);
    }
}
