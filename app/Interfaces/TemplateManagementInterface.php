<?php

declare(strict_types=1);

namespace App\Interfaces;

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
     * @param  array<string, mixed>  $data  Template data
     */
    public function createTemplate(int $gymId, array $data): WhatsAppTemplate;

    /**
     * Submit a template for verification/approval.
     *
     * @param  WhatsAppTemplate  $template  The template to submit
     * @return array{
     *     success: bool,
     *     status?: string,
     *     sid?: string,
     *     error?: string
     * }
     */
    public function submitTemplate(WhatsAppTemplate $template): array;

    /**
     * Sync template status from provider.
     *
     * @param  WhatsAppTemplate  $template  The template to sync
     */
    public function syncTemplateStatus(WhatsAppTemplate $template): void;

    /**
     * Get all available templates.
     *
     * @param  int  $gymId  The gym ID
     * @return array{
     *     success: bool,
     *     templates?: array,
     *     count?: int,
     *     error?: string
     * }
     */
    public function getTemplates(int $gymId): array;
}
