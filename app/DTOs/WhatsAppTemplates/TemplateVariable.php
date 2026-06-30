<?php

declare(strict_types=1);

namespace App\DTOs\WhatsAppTemplates;

use JsonSerializable;

/**
 * @phpstan-type TemplateVariableArray array{name: string, format: string, example: string, default_value?: string|null}
 */
readonly class TemplateVariable implements JsonSerializable
{
    public function __construct(
        public string $name,
        public string $format,
        public string $example,
        public ?string $defaultValue = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'format' => $this->format,
            'example' => $this->example,
            'default_value' => $this->defaultValue,
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            name: $data['name'],
            format: $data['format'] ?? 'text',
            example: $data['example'] ?? '',
            defaultValue: $data['default_value'] ?? null,
        );
    }
}
