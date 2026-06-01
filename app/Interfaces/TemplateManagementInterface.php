<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DTOs\WhatsAppTemplates\CreateTemplateData;
use App\DTOs\WhatsAppTemplates\TemplateCollectionResult;
use App\DTOs\WhatsAppTemplates\TemplateSubmissionResult;
use App\Models\WhatsAppTemplate;

/**
 * Template management operations interface.
 *
 * This interface defines WhatsApp template management operations.
 * Both Twilio and Meta WABA providers implement this interface.
 */
interface TemplateManagementInterface
{
    /**
     * Create a new WhatsApp template.
     *
     * @param  int  $gymId  The gym ID
     * @param  CreateTemplateData  $data  Template data
     */
    public function createTemplate(int $gymId, CreateTemplateData $data): WhatsAppTemplate;

    /**
     * Submit a template for approval.
     *
     * @param  WhatsAppTemplate|null  $template  Template to submit
     */
    public function submitTemplate(?WhatsAppTemplate $template = null): TemplateSubmissionResult;

    /**
     * Sync template status from provider.
     *
     * @param  WhatsAppTemplate  $template  The template to sync
     */
    public function syncTemplateStatus(WhatsAppTemplate $template): void;

    /**
     * Get all templates for a gym.
     *
     * @param  int  $gymId  The gym ID
     */
    public function getTemplates(int $gymId): TemplateCollectionResult;
}
