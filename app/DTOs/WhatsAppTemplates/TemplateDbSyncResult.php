<?php

declare(strict_types=1);

namespace App\DTOs\WhatsAppTemplates;

readonly class TemplateDbSyncResult
{
    public function __construct(
        public bool $success,
        public ?int $synced = null,
        public ?string $message = null,
        public ?string $error = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = ['success' => $this->success];

        if ($this->synced !== null) {
            $result['synced'] = $this->synced;
        }

        if ($this->message !== null) {
            $result['message'] = $this->message;
        }

        if ($this->error !== null) {
            $result['error'] = $this->error;
        }

        return $result;
    }
}
