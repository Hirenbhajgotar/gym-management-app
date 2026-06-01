<?php

declare(strict_types=1);

namespace App\DTOs\WhatsAppTemplates;

readonly class UpdateTemplateData
{
    public function __construct(
        public ?string $name = null,
        public ?string $display_name = null,
        public ?string $body = null,
        public ?string $category = null,
        public ?array $variable_placeholder = null,
        public ?array $variables = null,
        public ?int $variable_count = null,
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
            'variables' => $this->variables,
            'variable_count' => $this->variable_count,
            'is_default' => $this->is_default,
        ], fn ($value) => $value !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            display_name: $data['display_name'] ?? null,
            body: $data['body'] ?? null,
            category: $data['category'] ?? null,
            variable_placeholder: $data['variable_placeholder'] ?? null,
            variables: $data['variables'] ?? null,
            variable_count: $data['variable_count'] ?? null,
            is_default: $data['is_default'] ?? null,
        );
    }
}
