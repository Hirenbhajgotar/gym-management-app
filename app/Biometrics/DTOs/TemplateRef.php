<?php

declare(strict_types=1);

namespace App\Biometrics\DTOs;

readonly class TemplateRef
{
    public function __construct(
        public string $provider,
        public string $externalId,
        public array $meta = []
    ) {}

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);

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
        ]);
    }
}
