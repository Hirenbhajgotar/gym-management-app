<?php

declare(strict_types=1);

namespace App\DTOs\WhatsAppTemplates;

readonly class TemplateSubmissionResult
{
    public function __construct(
        public bool $success,
        public ?string $status = null,
        public ?string $sid = null,
        public ?string $templateId = null,
        public ?string $error = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = ['success' => $this->success];

        if ($this->status !== null) {
            $result['status'] = $this->status;
        }

        // Single unified key for the provider-assigned ID (Twilio sid or Meta templateId)
        $id = $this->templateId ?? $this->sid;
        if ($id !== null) {
            $result['template_id'] = $id;
        }

        if ($this->error !== null) {
            $result['error'] = $this->error;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromTwilioResponse(array $response): self
    {
        return new self(
            success: true,
            status: $response['status'] ?? 'PENDING',
            sid: $response['sid'] ?? null,
        );
    }

    public static function fromMetaResponse(string $templateId, string $status): self
    {
        return new self(
            success: true,
            status: $status,
            templateId: $templateId,
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
