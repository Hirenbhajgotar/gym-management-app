<?php

declare(strict_types=1);

namespace App\DTOs\WhatsAppTemplates;

use JsonSerializable;

readonly class TemplatePlaceholder implements JsonSerializable
{
    public function __construct(
        public string $placeholder,
        public string $name,
        public string $example,
        public string $type,
        public string $source,
    ) {}

    public function toArray(): array
    {
        return [
            'placeholder' => $this->placeholder,
            'name' => $this->name,
            'example' => $this->example,
            'type' => $this->type,
            'source' => $this->source,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public static function fromArray(array $data): static
    {
        return new static(
            placeholder: $data['placeholder'],
            name: $data['name'],
            example: $data['example'] ?? '',
            type: $data['type'] ?? 'text',
            source: $data['source'] ?? 'custom',
        );
    }
}
