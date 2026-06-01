<?php

declare(strict_types=1);

namespace App\DTOs\WhatsAppTemplates;

readonly class TemplateFetchResult
{
    public function __construct(
        public bool $success,
        public ?array $templates = null,
        public ?int $count = null,
        public ?string $error = null,
    ) {}

    public function toArray(): array
    {
        $result = ['success' => $this->success];

        if ($this->templates !== null) {
            $result['templates'] = $this->templates;
        }

        if ($this->count !== null) {
            $result['count'] = $this->count;
        }

        if ($this->error !== null) {
            $result['error'] = $this->error;
        }

        return $result;
    }
}
