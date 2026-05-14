<?php

declare(strict_types=1);

namespace App\DTOs\WhatsAppTemplates;

readonly class CreateTemplateData
{
    public function __construct(
        public string $name,
        public string $display_name,
        public string $body,
        public string $category,
        public ?array $variable_placeholder = null,
        public ?bool $is_default = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'display_name' => $this->display_name,
            'body' => $this->body,
            'category' => $this->category,
            'variable_placeholder' => $this->variable_placeholder,
            'is_default' => $this->is_default,
        ], fn ($value) => $value !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? throw new \InvalidArgumentException('Missing required field: name'),
            display_name: $data['display_name'] ?? throw new \InvalidArgumentException('Missing required field: display_name'),
            body: $data['body'] ?? throw new \InvalidArgumentException('Missing required field: body'),
            category: $data['category'] ?? 'utility',
            variable_placeholder: $data['variable_placeholder'] ?? null,
            is_default: $data['is_default'] ?? null,
        );
    }
}
