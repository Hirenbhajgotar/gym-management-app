<?php

declare(strict_types=1);

namespace App\DTOs\WhatsAppTemplates;

use App\Enums\TemplateCategoryEnum;
use InvalidArgumentException;

/**
 * @phpstan-type CreateTemplateDataArray array{name: string, display_name: string, body: string, category: string, language?: string|null, variable_placeholder?: array<string, mixed>|null, is_default?: bool|null}
 */
readonly class CreateTemplateData
{
    /**
     * @param  array<string, mixed>|null  $variable_placeholder
     */
    public function __construct(
        public string $name,
        public string $display_name,
        public string $body,
        public string $category,
        public ?string $language = null,
        public ?array $variable_placeholder = null,
        public ?bool $is_default = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'display_name' => $this->display_name,
            'body' => $this->body,
            'category' => $this->category,
            'language' => $this->language,
            'variable_placeholder' => $this->variable_placeholder,
            'is_default' => $this->is_default,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? throw new InvalidArgumentException('Missing required field: name'),
            display_name: $data['display_name'] ?? throw new InvalidArgumentException('Missing required field: display_name'),
            body: $data['body'] ?? throw new InvalidArgumentException('Missing required field: body'),
            category: $data['category'] ?? TemplateCategoryEnum::UTILITY->value,
            language: is_string($data['language'] ?? null) ? $data['language'] : null,
            variable_placeholder: $data['variable_placeholder'] ?? null,
            is_default: $data['is_default'] ?? null,
        );
    }
}
