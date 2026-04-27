<?php

namespace App\Services\Twilio;

use App\Interfaces\TemplateManagementInterface;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppTemplateManager;
use App\Services\WhatsAppTemplateService as BaseTemplateService;

/**
 * Twilio Template Service.
 *
 * Implements TemplateManagementInterface for template operations.
 */
class TwilioTemplateService implements TemplateManagementInterface
{
    public function __construct(protected int $gymId) {}

    /**
     * Create a new template.
     */
    public function createTemplate(int $gymId, array $data): WhatsAppTemplate
    {
        $service = app(BaseTemplateService::class);

        return $service->createTemplate($gymId, $data);
    }

    /**
     * Submit template for verification.
     */
    public function submitTemplate(WhatsAppTemplate $template): array
    {
        $service = app(BaseTemplateService::class);

        return $service->submitForVerification($template);
    }

    /**
     * Sync template status.
     */
    public function syncTemplateStatus(WhatsAppTemplate $template): void
    {
        $service = app(BaseTemplateService::class);
        $service->syncTemplateStatus($template);
    }

    /**
     * Get all available templates.
     */
    public function getTemplates(int $gymId): array
    {
        $templateManager = new WhatsAppTemplateManager((string) $gymId);

        return $templateManager->fetchTemplates();
    }
}
