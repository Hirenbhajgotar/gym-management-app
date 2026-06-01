<?php

declare(strict_types=1);

namespace App\DTOs\WhatsAppTemplates;

readonly class TemplateMasterSyncResult
{
    public function __construct(
        public bool $success,
        public ?int $syncedCount = null,
        public ?int $errorCount = null,
        public array $errors = [],
        public ?string $error = null,
    ) {}

    public function toArray(): array
    {
        $result = [
            'success' => $this->success,
            'errors' => $this->errors,
        ];

        if ($this->syncedCount !== null) {
            $result['synced_count'] = $this->syncedCount;
        }

        if ($this->errorCount !== null) {
            $result['error_count'] = $this->errorCount;
        }

        if ($this->error !== null) {
            $result['error'] = $this->error;
        }

        return $result;
    }

    public static function fromTwilioResponse(int $syncedCount, int $errorCount, array $errors): self
    {
        return new self(
            success: true,
            syncedCount: $syncedCount,
            errorCount: $errorCount,
            errors: $errors,
        );
    }

    public static function fromError(string $error): self
    {
        return new self(
            success: false,
            error: $error,
        );
    }
}
